<?php

namespace Tokenpass\Http\Controllers\Inventory;

use DB;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Input, \Exception, Session, Response, Cache, Config;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\XChainClient\WebHookReceiver;
use Tokenpass\Events\UserBalanceChanged;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\Provisional;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;

class InventoryController extends Controller
{

	/**
	 * Create a new authentication controller instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->middleware('auth');
	}


	public function index()
	{
        $authed_user = Auth::user();

		$addresses = Address::getAddressList($authed_user->id, null, true);
        foreach($addresses as $address){
            //remove some fields that the view doesnt need to know about
            unset($address->user_id);
            unset($address->xchain_address_id);
            unset($address->receive_monitor_id);
            unset($address->send_monitor_id);
        }
		$balances = Address::getAllUserBalances($authed_user->id);
		ksort($balances);
		$disabled_tokens = Address::getDisabledTokens($authed_user->id);
        $loans = Provisional::getUserOwnedPromises($authed_user->id);
		$balance_addresses = array();
		$address_labels = array();
        if($addresses){
            foreach ($addresses as $address) {
                // real balances
                $real_balances = Address::getAddressBalances($address->id, false, false);
                if($real_balances){
                    foreach ($real_balances as $asset => $amnt) {
                        if ($amnt <= 0) {
                            continue;
                        }
                        if (!isset($balance_addresses[$asset])) {
                            $balance_addresses[$asset] = array();
                        }
                        $balance_addresses[$asset][$address->address] = array('real' => $amnt, 'provisional' => array(), 'loans' => array());
                    }
                }

                // promises (received)
                $promises = Provisional::getAddressPromises($address->address);
                if($promises){
                    foreach ($promises as $promise) {
                        if (!isset($balance_addresses[$promise->asset])) {
                            $balance_addresses[$promise->asset] = array();
                        }
                        if (!isset($balance_addresses[$promise->asset][$address->address])) {
                            $balance_addresses[$promise->asset][$address->address] = array('real' => 0, 'provisional' => array(), 'loans' => array());
                        }
                        $ref_data = $promise->getRefData();
                        if(isset($ref_data['show_as'])){
                            if($ref_data['show_as'] == 'username' AND $promise->user_id > 0){
                                $promise_user = User::find($promise->user_id);
                                if($promise_user){
                                    $promise->source = $promise_user->username;
                                }
                            }
                        }
                        if(isset($ref_data['user'])){
                            unset($ref_data['user']);
                        }
                        $promise->ref_data = $ref_data;
                        unset($promise->user_id);
                        unset($promise->ref);
                        $balance_addresses[$promise->asset][$address->address]['provisional'][] = $promise;
                    }
                }

                // loans (debits)
                if($loans){
                    foreach($loans as $loan){
                        if($loan->source == $address->address){
                            if (!isset($balance_addresses[$loan->asset])) {
                                $balance_addresses[$loan->asset] = array();
                            }
                            if (!isset($balance_addresses[$loan->asset][$address->address])) {
                                $balance_addresses[$loan->asset][$address->address] = array('real' => 0, 'provisional' => array(), 'loans' => array());
                            }
                            $ref_data = $loan->getRefData();
                            if(isset($ref_data['user'])){
                                $get_user = User::find($ref_data['user']);
                                if($get_user){
                                    $loan->destination = $get_user->username;
                                }
                            }
                            $balance_addresses[$loan->asset][$address->address]['loans'][] = $loan;
                        }
                    }
                }
                $address_labels[$address->address] = trim($address->label);
            }
        }
        if($loans){
            foreach($loans as $k => $loan){
                if(isset($balances[$loan->asset])){
                    $balances[$loan->asset] -= $loan->quantity;
                }
                else{
                    $balances[$loan->asset] = 0 - $loan->quantity;
                }
                $ref_data = $loan->getRefdata();
                if(isset($ref_data['user'])){
                    unset($ref_data['user']);
                }
                if(!isset($ref_data['show_as'])){
                    $ref_data['show_as'] = 'address';
                }
                $loans[$k]->ref_data = $ref_data;
                $loans[$k]->date = null;
                if($loan->expiration > 0){
                    $loans[$k]->date = date('Y-m-d', $loan->expiration);
                }
                $loans[$k]->show_as = 'username';
                if(isset($ref_data['show_as'])){
                    $loans[$k]->show_as = $ref_data['show_as'];
                }
                unset($loans[$k]->ref);
                unset($loans[$k]->user_id);
            }
        }
        $bvam = new BVAMClient(env('BVAM_URL'));
        $bvam_balances = $balances;
        unset($bvam_balances['BTC']);
        $keys = array_keys($bvam_balances);
        $bvam_data = array();
        if(count($keys) > 0){
            try{
                $bvam_data = $bvam->getMultipleAssetsInfo($keys);
            }
            catch(Exception $e){
                
            }
        }
        
		$vars = [
            'addresses'         => $addresses,
            'addresses_map'     => collect($addresses)->keyBy('address')->toArray(),
            'address_labels'    => $address_labels,
            'balances'          => $balances,
            'balance_addresses' => $balance_addresses,
            'disabled_tokens'   => $disabled_tokens,
            'loans'             => $loans,
            'bvam'              => $bvam_data,
            ];

		return view('inventory.index', $vars);
	}

	public function registerAddress()
	{
		//get input
		$input = Input::all();
        $authed_user = Auth::user();


		//check required fields
		if (!isset($input['address']) OR trim($input['address']) == '') {
			return $this->ajaxEnabledErrorResponse('Bitcoin address required', route('inventory.pockets'));
		}

		//setup data
		$address = trim($input['address']);
		$label = '';
		if (isset($input['label'])) {
			$label = trim(htmlentities($input['label']));
		}
		$public = 0;
		if (isset($input['public']) AND intval($input['public']) === 1) {
			$public = 1;
		}

		//validate address
		try {
			$xchain = app('Tokenly\XChainClient\Client');
			$validate = $xchain->validateAddress($address);
		} catch (Exception $e) {
			return $this->ajaxEnabledErrorResponse($e->getMessage(), route('inventory.pockets'));
		}

		if (!$validate OR !$validate['result']) {
			return $this->ajaxEnabledErrorResponse('Please enter a valid bitcoin address', route('inventory.pockets'));
		}

		//check if they have this address registered already
		$user_addresses = Address::getAddressList($authed_user->id, null, null);
		if ($user_addresses AND count($user_addresses) > 0) {
			$found = false;
			foreach ($user_addresses as $addr) {
				if ($addr->address == $address) {
					$found = true;
					break;
				}
			}
			if ($found) {
				return $this->ajaxEnabledErrorResponse('Address has already been registered for this account', route('inventory.pockets'));
			}
		}

		//register address
		$new_address = app('Tokenpass\Repositories\AddressRepository')->create([
			'user_id' => $authed_user->id,
			'type' => 'btc',
			'address' => $address,
			'label' => $label,
			'verified' => 0,
			'public' => $public,
		]);
		$save = (!!$new_address);

		if (!$save) {
			return $this->ajaxEnabledErrorResponse('Error saving address', route('inventory.pockets'), 500);
		}

		// sync with XChain
		$new_address->syncWithXChain();
		$new_address->setUpPayToVerifyMethod();

        // fire a balance changed event
        try{
            event(new UserBalanceChanged($authed_user));
        }
        catch(Exception $e){
            Log::error('Error pushing balance change event: '.$e->getMessage());
        }

		return $this->ajaxEnabledSuccessResponse('Bitcoin address registered!', route('inventory.pockets'));
	}

	public function deleteAddress($address)
	{
        $authed_user = Auth::user();

		$get = Address::where('user_id', $authed_user->id)->where('address', $address)->first();
		if (!$get) {
			return $this->ajaxEnabledErrorResponse('Address not found', route('inventory.pockets'), 404);
		} else {
			$delete = $get->delete();
			if (!$delete) {
				return $this->ajaxEnabledErrorResponse('Error updating address', route('inventory.pockets'), 500);
			} else {
				return $this->ajaxEnabledSuccessResponse('Address deleted!', route('inventory.pockets'));
			}
		}
	}

	public function editAddress($address)
	{
        $authed_user = Auth::user();

		$get = Address::where('user_id', $authed_user->id)->where('address', $address)->first();
        if ($get['pseudo']) {
            return $this->ajaxEnabledErrorResponse('Unable to edit pseudo address.', route('inventory.pockets'), 400);
        }

		if (!$get) {
			return $this->ajaxEnabledErrorResponse('Address not found', route('inventory.pockets'), 404);
		} else {
			$input = Input::all();

			if (isset($input['label'])) {
				$get->label = trim(htmlentities($input['label']));
			}

			$active = 0;
			if (isset($input['active']) AND intval($input['active']) == 1) {
				$active = 1;
			}
			$get->active_toggle = $active;

			$public = 0;
			if (isset($input['public']) AND intval($input['public']) == 1) {
				$public = 1;
			}
			$get->public = $public;

			$login_toggle = 0;
			if (!$get->from_api AND isset($input['login']) AND intval($input['login']) == 1 AND $get->second_factor_toggle == 0) {
				$login_toggle = 1;
			}
			$get->login_toggle = $login_toggle;

			$second_factor = 0;
			if (!$get->from_api AND isset($input['second_factor']) AND intval($input['second_factor']) == 1 AND $get->login_toggle == 0) {
				$second_factor = 1;
			}
			$get->second_factor_toggle = $second_factor;

			if (isset($input['notes'])) {
				$get->notes = trim(htmlentities($input['notes']));
			}

			$save = $get->save();

			if (!$save) {
				return $this->ajaxEnabledErrorResponse('Error updating address', route('inventory.pockets'), 500);
			} else {
				return $this->ajaxEnabledSuccessResponse('Address updated!', route('inventory.pockets'));
			}
		}
	}

	public function toggleAsset($asset)
	{
        $authed_user = Auth::user();
		$output = array('result' => false);
		$response_code = 200;

		$disabled_tokens = json_decode(UserMeta::getMeta($authed_user->id, 'disabled_tokens'), true);
		if (!is_array($disabled_tokens)) {
			$disabled_tokens = array();
		}

		$input = Input::all();
		if (!isset($input['toggle'])) {
			$output['error'] = 'Toggle option required';
			$response_code = 400;
		} else {
			$toggle_val = $input['toggle'];
			if ($toggle_val == 'true' OR $toggle_val === true) {
				$toggle_val = 1;
			} else {
				$toggle_val = 0;
			}

			if ($toggle_val == 1 AND in_array($asset, $disabled_tokens)) {
				$k = array_search($asset, $disabled_tokens);
				unset($disabled_tokens[$k]);
				$disabled_tokens = array_values($disabled_tokens);
			} elseif ($toggle_val == 0 AND !in_array($asset, $disabled_tokens)) {
				$disabled_tokens[] = $asset;
			}
			$save = UserMeta::setMeta($authed_user->id, 'disabled_tokens', json_encode($disabled_tokens));
			if (!$save) {
				$output['error'] = 'Error updating list of disabled tokens';
				$response_code = 500;
			} else {
				$output['result'] = true;
			}
		}

        // fire a balance changed event
        try{
            event(new UserBalanceChanged($authed_user));
        }
        catch(Exception $e){
            Log::error('Error pushing balance change event: '.$e->getMessage());
        }        

		return Response::json($output, $response_code);
	}

	public function verifyAddressOwnership($address)
	{

        $authed_user = Auth::user();

        $existing_addresses = Address::where('address', $address)->get();
        foreach($existing_addresses as $item) {
            if ($item->user_id != Auth::user()->id) {
                return $this->ajaxEnabledErrorResponse('The address '.$address.' is already in use by another account', route('inventory.pockets'), 400);
            }
        }

		$get = Address::where('user_id', $authed_user->id)->where('address', $address)->first();

		if(!$get){
            return $this->ajaxEnabledErrorResponse('Address not found', route('inventory.pockets'), 404);
		}
		else{
			$input = Input::all();
            if(isset($input['signature'])){
                $input['sig'] = str_replace(' ', '+', urldecode($input['signature']));
            }
			if(!isset($input['sig']) OR trim($input['sig']) == ''){
                return $this->ajaxEnabledErrorResponse('Signature required', route('inventory.pockets'), 400);
			}
			else{
				$sig = Address::extractSignature($input['sig']);
				$xchain = app('Tokenly\XChainClient\Client');
                $message = Session::get($address);
                Session::set($address, '');
                if(trim($message) == ''){
                    return $this->ajaxEnabledErrorResponse('Verification message not found', route('inventory.pockets'), 400);
                }
				$verify_message = $xchain->verifyMessage($get->address, $sig, $message);
				$verified = false;
				if($verify_message AND $verify_message['result']){
					$verified = true;
				}

				if(!$verified){
                    return $this->ajaxEnabledErrorResponse('Signature for address '.$address.' is not valid', route('inventory.pockets'), 400);
				}
				else{
					$get->verified = 1;
					$save = $get->save();

					if(!$save){
                        return $this->ajaxEnabledErrorResponse('Error updating address '.$address, route('inventory.pockets'), 400);
					}
					else{
						//Address::updateUserBalances($authed_user->id); //do a fresh inventory update (disabled for now, too slow)
                        return $this->ajaxEnabledSuccessResponse('Address '.$address.' ownership proved successfully!', route('inventory.pockets'));
					}
				}
			}
		}
		return redirect(route('inventory.pockets'));
	}
    

    
    public function checkForVerifySignature(Request $request)
    {
        $user = Auth::user();
        if(!$user){
            return response()->json(array('error' => 'Not logged in'), 400);
        }
        $unverified = Address::where('verified', 0)->where('user_id', $user->id)->get();
        $output = array();
        if($unverified){
            $found = false;
            foreach($unverified as $k => $address){
                $code = Session::get($address->address);
                $hash = hash('sha256', $code);
                $cache_sig = Cache::get($hash.'_sig');
                if($cache_sig){
                    $output[] = array('signature' => $cache_sig, 'address' => $address->address);
                }
            }
        }

        //Check if address is already verified
        //To avoid doing two ajax calls, we use the same method for checking if address was verified by payment
        $input = \Illuminate\Support\Facades\Input::all();
        $current_pocket_address = $input['current_address'];
        $address = Address::where('address', $current_pocket_address)->first();
        if($address AND $address->verified == 1) {
            $success_message = 'Address ownership proven successfully!';
            Session::flash('message', $success_message);
            Session::flash('message-class', 'alert-success');
            return $this->ajaxEnabledSuccessResponse($success_message, route('inventory.pockets'));
        }

        return response()->json(array('results' => $output));
    }

	public function refreshBalances()
	{
        $authed_user = Auth::user();

        $update_success = app('Tokenpass\Repositories\AddressRepository')->updateUserBalances($authed_user->id);
		if(!$update_success){
			Session::flash('message', 'Error updating balances');
			Session::flash('message-class', 'alert-danger');
		}
		else{
			Session::flash('message', 'Token inventory balances updated!');
			Session::flash('message-class', 'alert-success');
		}
		return redirect('inventory');
	}

    public function checkPageRefresh()
    {
        $output = array('result' => false);
        $user = Auth::user();
        if(!$user){
             return Response::json($output, 404);
        }

        $check_refresh = intval(UserMeta::getMeta($user->id, 'force_inventory_page_refresh'));
        if($check_refresh === 1){
            $output['result'] = true;
            UserMeta::setMeta($user->id, 'force_inventory_page_refresh', 0);
            $refresh_message = UserMeta::getMeta($user->id, 'inventory_refresh_message');
			Session::flash('message', $refresh_message);
			Session::flash('message-class', 'alert-success');
        }
        return Response::json($output);
    }

    public function getPockets()
    {
        $authed_user = Auth::user();

		$addresses = [];
		foreach(Address::getAddressList($authed_user->id, null, null) as $address) {
            if ($address->isPseudoAddress()) { continue; }

			// Generate message for signing and flash for POST results
			if ($address->verified == 0) {
				$address['secure_code'] = Address::getSecureCodeGeneration();
                $address['msg_hash'] = hash('sha256', $address['secure_code']);
				Session::set($address->address, $address['secure_code']);
                Cache::put($address['msg_hash'].'_msg', $address['secure_code'], 600);
                Cache::put($address['msg_hash'], $authed_user->id, 600);
			}
            //remove some fields that the view doesnt need to know about
            unset($address->user_id);
            unset($address->xchain_address_id);
            unset($address->receive_monitor_id);
            unset($address->send_monitor_id);

            $addresses[] = $address;
		}
		
		return view('inventory.pockets', array(
			'addresses' => $addresses,
		));
    }
    
    public function lendAsset($address, $asset)
    {
        //check valid verified address owned by user
        $user = Auth::user();
        $source_address_model = Address::where('address', $address)->where('verified', 1)->first();
        if(!$user OR !$source_address_model OR $source_address_model->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Address not found', route('inventory'), 404);
        }
        
        //get input
        $input = Input::all();
        
        //get quantity
        if(!isset($input['quantity'])){
            return $this->ajaxEnabledErrorResponse('Quantity required', route('inventory'), 400);
        }
        $quantity = round(floatval($input['quantity']) * 100000000); //quantity in satoshis
        if($quantity <= 0){
            return $this->ajaxEnabledErrorResponse('Invalid quantity', route('inventory'), 400);
        }
        
        //get valid asset
        $asset_db = DB::table('address_balances')->where('address_id', $source_address_model->id)->where('asset', $asset)->first();
        if(!$asset_db){
            return $this->ajaxEnabledErrorResponse('Invalid asset', route('inventory'), 400);
        }
        
        //get expiration
        $time = time();
        $expiration = null;
        if(trim($input['end_date']) != ''){
            if(isset($input['end_time'])){
                $input['end_date'] .= ' '.$input['end_time'];
            }
            $expiration = strtotime($input['end_date']);
            if($expiration <= $time){
                return $this->ajaxEnabledErrorResponse('Expiration date must be sometime in the future', route('inventory'), 400);
            }
        }
        
        //get custom note
        $note = null;
        if(isset($input['note'])){
            $note = trim(htmlentities($input['note']));
        }
        
        //get valid destination
        if(!isset($input['lendee']) OR trim($input['lendee']) == ''){
            return $this->ajaxEnabledErrorResponse('Lendee required', route('inventory'), 400);
        }
        $destination = trim($input['lendee']);
        $ref = null;
        //check first if user, then if bitcoin address
        $destination_user = User::where('username', $destination)->first();
        if($destination_user){
            if($destination_user->id == $user->id){
                return $this->ajaxEnabledErrorResponse('Cannot lend to self', route('inventory'), 400);
            }
            //use their first active verified address
            $first_address = Address::where('user_id', $destination_user->id)->where('active_toggle', 1)->where('verified', 1)->first();
            if(!$first_address){
                $first_address = app(PseudoAddressManager::class)->ensurePseudoAddressForUser($destination_user);
                // return $this->ajaxEnabledErrorResponse('Lendee does not have any verified addresses', route('inventory'), 400);
            }
            $destination = $first_address->address;
            $ref = 'user:'.$destination_user->id;
        }
        else{
            //check if valid bitcoin address
            try {
                $xchain = app('Tokenly\XChainClient\Client');
                $validate_address = $xchain->validateAddress($destination);
            } catch (Exception $e) {
                return $this->ajaxEnabledErrorResponse($e->getMessage(), route('inventory'), 500);
            }
            if (!$validate_address OR !$validate_address['result']) {
                return $this->ajaxEnabledErrorResponse('Please enter a valid bitcoin address', route('inventory'), 400);
            }
            
            $destination_address_model = Address::where('address', $destination)->where('verified', 1)->first();
            if($destination_address_model){
                $destination_user = User::find($destination_address_model->user_id);
            }
        }
        if($destination == $address){
            return $this->ajaxEnabledErrorResponse('Cannot lend to source address', route('inventory'), 400);
        }
        
        
        //decide if they want to reveeal source pocket address or show as username
        $show_as = null;
        if(isset($input['show_as'])){
            switch($input['show_as']){
                case 'address':
                    $show_as = 'address';
                    break;
                case 'username':
                default:
                    $show_as = 'username';
                    break;
            }
            $add_ref = 'show_as:'.$show_as;
            if($ref != null){
                $ref .= ','.$add_ref;
            }
            else{
                $ref = $add_ref;
            }
        }
        
        //get total balance of all promises made including this one
        $total_promised = Provisional::getTotalPromised($address, $asset, $quantity);
        
        //check with crypto backend that the address really has enough tokens
        $valid_balance = false;
        try{
            $valid_balance = Provisional::checkValidPromisedAmount($address, $asset, $total_promised);
        }
        catch(Exception $e){
            return $this->ajaxEnabledErrorResponse('Error validating promise balance: '.$e->getMessage(), route('inventory'), 500);
        }
        Log::debug("\$valid_balance=".json_encode($valid_balance, 192));
        if(is_array($valid_balance) AND !$valid_balance['valid']){
            return $this->ajaxEnabledErrorResponse('Not enough real balance to lend this amount', route('inventory'), 500);
        }
        elseif(!$valid_balance){
            return $this->ajaxEnabledErrorResponse('Unknown error validating promise balance', route('inventory'), 500);
        }
        
        //create the provisional/promise transaction
        $promise = new Provisional;
        $promise->source = $address;
        $promise->asset = $asset;
        $promise->destination = $destination;
        $promise->quantity = $quantity;
        $promise->expiration = $expiration;
        $promise->ref = $ref;
        $promise->user_id = $user->id;
        $promise->note = $note;
        
        $save = $promise->save();
        if(!$save){
            return $this->ajaxEnabledErrorResponse('Error saving promise transaction', route('inventory'), 500);
        }
        else{
            if($destination_user){
                $notify_data = array('promise' => $promise, 'lender' => $user, 'show_as' => $show_as);
                $destination_user->notify('emails.loans.new-loan', 'New TCA loan for '.$promise->asset.' received '.date('Y/m/d'), $notify_data);
            }
            return $this->ajaxEnabledSuccessResponse($asset.' succesfully lent!', route('inventory'));
        }
    }
    
    public function deleteLoan($id)
    {
        $get = Provisional::find($id);
        $user = Auth::user();
        if(!$user OR !$get OR $get->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('TCA loan not found', route('inventory'), 404);
        }
        $destination = $get->destination;
        $delete = $get->delete();
        if(!$delete){
            return $this->ajaxEnabledErrorResponse('Error cancelling TCA loan', route('inventory'), 500);
        }
        else{
            $get_user = Address::where('address', $destination)->where('verified', 1)->first();
            if($get_user){
                $get_user = $get_user->user();
            }
            if($get_user){
                $notify_data = array('promise' => $get, 'lender' => $user);
                $get_user->notify('emails.loans.delete-loan', 'TCA loan for '.$get->asset.' cancelled '.date('Y/m/d'), $notify_data);
            }
            return $this->ajaxEnabledSuccessResponse('TCA loan cancelled', route('inventory'));
        }
    }
    
    public function editLoan($id)
    {
        $get = Provisional::find($id);
        $user = Auth::user();
        if(!$user OR !$get OR $get->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('TCA loan not found', route('inventory'), 404);
        }
        $input = Input::all();
        
        $time = time();
        $expiration = null;
        if(trim($input['end_date']) != ''){
            if(isset($input['end_time'])){
                $input['end_date'] .= ' '.$input['end_time'];
            }            
            $expiration = strtotime($input['end_date']);
            if($expiration <= $time){
                return $this->ajaxEnabledErrorResponse('Expiration date must be sometime in the future', route('inventory'), 400);
            }
        }      
        
        $ref_data = $get->getRefData();
        if(isset($input['show_as'])){
            $show_as = null;
            switch($input['show_as']){
                case 'address':
                    $show_as = 'address';
                    break;
                case 'username':
                default:
                    $show_as = 'username';
                    break;
            }
            $ref_data['show_as'] = $show_as;
        }          
        $join_ref = Provisional::joinRefData($ref_data);
        $old_expiration = $get->expiration;
        $get->expiration = $expiration;
        $get->ref = $join_ref;
        $get->updated_at = date('Y-m-d H:i:s');
        
        $save = $get->save();
        if(!$save){
            return $this->ajaxEnabledErrorResponse('Error saving promise transaction', route('inventory'), 500);
        }
        else{
            if($old_expiration != $expiration){
                //send notification if expiration date has been changed
                $get_user = Address::where('address', $get->destination)->where('verified', 1)->first();
                if($get_user){
                    $get_user = $get_user->user();
                }
                if($get_user){
                    $notify_data = array('promise' => $get, 'lender' => $user, 'old_expiration' => $old_expiration);
                    $get_user->notify('emails.loans.edit-loan', 'TCA loan for '.$get->asset.' updated '.date('Y/m/d'), $notify_data);
                }
            }
            return $this->ajaxEnabledSuccessResponse('Loan successfully modified!', route('inventory'));
        }        
    }
    
    public function getTokenDetails($token)
    {
        $bvam = new BVAMClient(env('BVAM_URL'));
        $bvam_data = $bvam->getAssetInfo($token);
        $bvam_labels = Config::get('tokenpass.supported_bvam_labels');
        $user = Auth::user();
        $balance = 0;
        if($user){
            $balance = Address::getUserTokenBalance($user, $token);
        }
        
        return view('inventory.token-details', array('token_name' => $token, 'bvam' => $bvam_data, 'bvam_labels' => $bvam_labels, 'balance' => $balance));
    }

    // ------------------------------------------------------------------------
    protected function ajaxEnabledErrorResponse($error_message, $redirect_url, $error_code = 400) {
        if (Request::ajax()) {
            return Response::json(['success' => false, 'error' => $error_message], $error_code);
        }

        Session::flash('message', $error_message);
        Session::flash('message-class', 'alert-danger');
        return redirect($redirect_url);
    }

    protected function ajaxEnabledSuccessResponse($success_message, $redirect_url, $http_code = 200) {
        if (Request::ajax()) {
            return Response::json([
                'success'     => true,
                'message'     => $success_message,
                'redirectUrl' => $redirect_url,
            ], $http_code);
        }

        Session::flash('message', $success_message);
        Session::flash('message-class', 'alert-success');


        return redirect($redirect_url);
    }
}
