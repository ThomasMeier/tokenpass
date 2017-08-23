<?php
namespace Tokenpass\Http\Controllers\API;
use DB, Exception, Response, Input, Hash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\Provisional;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\OAuth\Facade\OAuthClientGuard;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Util\BitcoinUtil;

class APIProvisionalController extends Controller
{

    use DispatchesJobs;

    public function __construct(OAuthClientRepository $oauth_client_repository, ClientConnectionRepository $client_connection_repository, UserRepository $user_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;  
        $this->user_repository = $user_repository;
    }

    public function registerProvisionalTCASourceAddress() {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        $oauth_client = OAuthClientGuard::oauthClient();
                
        //check inputs
        if(!isset($input['address'])){
            $output['error'] = 'Address required';
            return Response::json($output, 400);
        }
        
        //check for proof of ownership unless they have special API privilege
        if(!$oauth_client->hasPrivilege('bypass-source-proof')){
            if(!isset($input['proof'])){
                $output['error'] = 'Proof required';
                return Response::json($output, 400);
            }        

            //verify signed message on xchain
            $sig_message = Provisional::getProofMessage($input['address'], $oauth_client['id']);
            $xchain = app('Tokenly\XChainClient\Client');
            try{
                $verify = $xchain->verifyMessage($input['address'], $input['proof'], $sig_message);
            }
            catch(Exception $e){
                $verify = false;
            }
            if(!$verify OR !isset($verify['result']) OR !$verify['result']){
                $output['error'] = 'Proof signature invalid';
                return Response::json($output, 400);
            }
        }
        else{
            $input['proof'] = 'bypassed';
        }
        
        $asset_list = null;
        if(isset($input['assets'])){
            if(!is_array($input['assets']) AND !is_object($input['assets'])){
                // split on commas and trim whitespace
                $input['assets'] = collect(explode(',', $input['assets']))
                    ->map(function($asset) {
                        return trim($asset);
                    })
                    ->filter(function($asset) {
                        return (strlen($asset) > 0);
                    });
            }
            $asset_list = json_encode($input['assets']);
        }
        
        $get = DB::table('provisional_tca_addresses')
                ->where('address', $input['address'])->where('client_id', $oauth_client['id'])
                ->first();
                
        $time = date('Y-m-d H:i:s');       
        if(!$get){
            //add new entry
            $data = array('address' => $input['address'], 'proof' => $input['proof'], 
                          'client_id' => $oauth_client['id'], 'assets' => $asset_list,
                          'created_at' => $time, 'updated_at' => $time);
            $update = DB::table('provisional_tca_addresses')->insert($data);
        }
        else{
            //update entry
            $data = array('proof' => $input['proof'], 'assets' => $asset_list, 'updated_at' => $time);
            $update = DB::table('provisional_tca_addresses')->where('id', $get->id)->update($data);
        }
        
        if(!$update){
            $output['error'] = 'Error registering provisional TCA address';
            return Response::json($output, 500);
        }
        
        $output['result'] = true;
        
        //if applicable, assign this source address as a pocket address for a specific user
        if(isset($input['assign_user']) AND trim($input['assign_user']) != '' AND isset($input['assign_user_hash'])){
            //for now, only allow this for apps with special API permission
            if($oauth_client->hasPrivilege('assign-source-address-any-user')){
                $username = trim($input['assign_user']);
                //find the user
                $assign_user = User::where('username', $username)->orWhere('slug', $username)->orWhere('email', $username)->first();
                if($assign_user){
                    //check against hash of tokenly_uuid for extra verification
                    $real_hash = hash('sha256', $assign_user->uuid);
                    if($real_hash != $input['assign_user_hash']){
                        $output['error'] = 'Incorrect assign_user_hash parameter';
                        return Response::json($output, 400);
                    }
                    //check if this address happens to already be registered
                    $find_address = Address::where('address', $input['address'])->first();
                    if(!$find_address){
                        //register the address
                        $label = null;
                        if(isset($input['assign_user_label'])){
                            $label = trim($input['assign_user_label']);
                        }                        
                        $user_address = app('Tokenpass\Repositories\AddressRepository')->create([
                            'user_id' => $assign_user->id,
                            'type' => 'btc',
                            'address' => $input['address'],
                            'label' => $label,
                            'verified' => 1,
                            'active_toggle' => 1,
                            'public' => 0,
                            'from_api' => 1
                        ]);                        

                        if(!$user_address){
                            $output['error'] = 'Error assigning provisional TCA address to user '.$username;
                            return Response::json($output, 500);
                        }
                        
                        try{
                            $user_address->syncWithXChain(); //sets up xchain_address_id and send/receive monitors
                        }
                        catch(Exception $e){
                            $output['error'] = 'Error syncing assigned address with xchain';
                            return Response::json($output, 500);
                        }
                    }
                }
            }
        }
        ////
        
        return Response::json($output);
    }

    public function deleteProvisionalTCASourceAddress($address) {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        
        $oauth_client = OAuthClientGuard::oauthClient();
        $get = DB::table('provisional_tca_addresses')
                ->where('client_id', $oauth_client['id'])->where('address', $address)->first();
        
        if(!$get){
            $output['error'] = 'Provisional source address not found';
            return Response::json($output, 404);
        }
        
        $delete = DB::table('provisional_tca_addresses')->where('id', $get->id)->delete();
        
        if(!$delete){
            $output['error'] = 'Error deleting provisional source address';
            return Response::json($output, 500);
        }
        
        $output['result'] = true;
        return Response::json($output);
    }

    public function getProvisionalTCASourceAddressList() {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        
        $oauth_client = OAuthClientGuard::oauthClient();
        $list = DB::table('provisional_tca_addresses')->where('client_id', $oauth_client['id'])->get();
        
        $output['proof_suffix'] = Provisional::getProofMessage(null, $oauth_client['id']);
        $output['whitelist'] = array();
        $output['result'] = true;
        if($list){
            foreach($list as $item){
                $output['whitelist'][$item->address] = array('address' => $item->address, 'assets' => json_decode($item->assets, true));
            }
        }
        return Response::json($output);
    }

    public function registerProvisionalTCATransaction() {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        
        // $user = self::get_oauth_user();
        // $userId = 0;
        // if($user){
        //     $userId = $user->id;
        // }
        
        //check if a valid application client_id
        $oauth_client = OAuthClientGuard::oauthClient();
        
        //check basic required fields
        $req = array('source', 'destination', 'asset', 'quantity');
        foreach($req as $required){
            if(!isset($input[$required]) OR trim($input[$required]) == ''){
                $output['error'] = $required.' required';
                return Response::json($output, 400);
            }
        }
        
        //check valid destination
        $destination = trim($input['destination']);
        $add_ref = null;
        if(strpos($destination, 'user:') === 0){
            //use an email as destination
            $destination = substr($destination, 5);
            $destination_user = User::where('username', $destination)->first();
            if($destination_user){
                // if($user AND $destination_user->id == $user->id){
                //     $output['error'] = 'Cannot make promise to self';
                //     return Response::json($output, 400);                    
                // }
                //use their first active verified address
                $first_address = Address::where('user_id', $destination_user->id)->where('active_toggle', 1)->where('verified', 1)->first();
                if(!$first_address){
                    // $output['error'] = 'Destination user does not have any verified addresses';
                    // return Response::json($output, 400);                    
                    $first_address = app(PseudoAddressManager::class)->ensurePseudoAddressForUser($destination_user);
                }
                $destination = $first_address->address;
                $add_ref = 'user:'.$destination_user->id;
            }
            else{
                $output['error'] = 'User not found';
                return Response::json($output, 404);        
            }            
        }
        elseif(strpos($destination, 'email:') === 0) {
            //use a username as destination
            $destination = substr($destination, 6);
            $destination_user = User::where('email', $destination)->first();
            if($destination_user){
                // if($user AND $destination_user->id == $user->id){
                //     $output['error'] = 'Cannot make promise to self';
                //     return Response::json($output, 400);
                // }
                //use their first active verified address
                $first_address = Address::where('user_id', $destination_user->id)->where('active_toggle', 1)->where('verified', 1)->first();
                if(!$first_address){
                    // $output['error'] = 'Destination user does not have any verified addresses';
                    // return Response::json($output, 400);
                    $first_address = app(PseudoAddressManager::class)->ensurePseudoAddressForUser($destination_user);
                }
                $destination = $first_address->address;
                $add_ref = 'user:'.$destination_user->id;
            }
        }
        else{
            //check if valid bitcoin address
            $address_is_valid = BitcoinUtil::isValidBitcoinAddress($destination);
            if (!$address_is_valid) {
                $output['error'] = 'Please enter a valid bitcoin address';
                return Response::json($output, 400);                   
            }
        }
        $input['destination'] = $destination;
        if($input['destination'] == $input['source']){
            $output['error'] = 'Cannot make promise to source address';
            return Response::json($output, 400);                   
        }
        
        //make sure this is a already whitelisted source address
        $get_source = DB::table('provisional_tca_addresses')
                        ->where('address', $input['source'])
                        ->where('client_id', $oauth_client['id'])->first();

        // if(!$get_source){
        //     if($user){
        //         //attempt to use a regular verified pocket address instead
        //         $get_source = Address::where('address', $input['source'])->where('verified', 1)->where('active_toggle', 1)->first();
        //         if($get_source AND $get_source->user_id != $user->id){
        //             $get_source = false;
        //         }
        //     }
        // }

        if(!$get_source){
            $output['error'] = 'Source address not on provisional whitelist';
            return Response::json($output, 400);
        }
        
        //check if whitelisted source address is resricted to specific assets
        if(trim($get_source->assets) != ''){
            $valid_assets = json_decode($get_source->assets, true);
            if(!in_array($input['asset'], $valid_assets)){
                $output['error'] = 'Asset not allowed for this provisional source address. Allowed: '.join(', ',$valid_assets);
                return Response::json($output, 400);
            }
        }
        
        //check txid/fingerprint, and make sure same one isn't submitted
        $txid = null;
        $fingerprint = null;
        $ref = null;
        $set_ref = null;
        $get_existing = DB::table('provisional_tca_txs');
        $check_exist = false;
        if(isset($input['txid']) AND trim($input['txid']) != ''){
            $get_existing = $get_existing->where('txid', $input['txid']);
            $txid = $input['txid'];
            $check_exist = true;
        }
        if(isset($input['fingerprint']) AND trim($input['fingerprint']) != ''){
            $get_existing = $get_existing->where('fingerprint', $input['fingerprint']);
            $fingerprint = $input['fingerprint'];
            $check_exist = true;
        }
        if(isset($input['ref']) AND trim($input['ref']) != ''){
            $ref = $input['ref'];
            $set_ref = $ref;
            if($add_ref != null){
                $ref .= ','.$add_ref;
            }
        }
        else{
            $ref = $add_ref;
        }
        if($check_exist){
            $get_existing = $get_existing->first();
            if($get_existing){
                $output['error'] = 'Provisional transaction with matching txid or fingerprint already exists';
                return Response::json($output, 400);
            }
        }
        
        //check valid quantity
        $quantity = intval($input['quantity']);
        if($quantity <= 0){
            $output['error'] = 'Invalid quantity, must be > 0';
            return Response::json($output, 400);
        }
        
        //check valid expiration
        $time = time();
        $expiration = null;
        if(isset($input['expiration'])){
            if(!is_int($input['expiration'])){
                $input['expiration'] = strtotime($input['expiration']);
            }
            if($input['expiration'] <= $time){
                $output['error'] = 'Invalid expiration, must be set to the future';
                return Response::json($output, 400);
            }
            $expiration = $input['expiration'];
        }

        //check for custom note
        $note = null;
        if(isset($input['note'])){
            $note = trim(htmlentities($input['note']));
        }
        
        //make sure the source address has sufficient balance to cover all its token promises
        try{
            $total_promised = Provisional::getTotalPromised($input['source'], $input['asset'], $quantity);
            $valid_balance = Provisional::checkValidPromisedAmount($input['source'], $input['asset'], $total_promised);
        }
        catch(Exception $e){
            $output['error'] = $e->getMessage();
            return Response::json($output, 500);
        }

        if(!$valid_balance['valid']){
            $output['error'] = 'Source address has insufficient asset balance to promise this transaction ('.round($total_promised/100000000,8).' '.$input['asset'].' promised and only balance of '.round($valid_balance['balance']/100000000,8).')';
            return Response::json($output, 400);
        }
        
        //setup the actual provisional transaction
        $date = date('Y-m-d H:i:s');
        $tx_data = array();
        $tx_data['source'] = $input['source'];
        $tx_data['destination'] = $input['destination'];
        $tx_data['asset'] = $input['asset'];
        $tx_data['quantity'] = $quantity;
        $tx_data['fingerprint'] = $fingerprint;
        $tx_data['txid'] = $txid;
        $tx_data['ref'] = $ref;
        $tx_data['expiration'] = $expiration;
        $tx_data['created_at'] = $date;
        $tx_data['updated_at'] = $date;
        $tx_data['pseudo'] = 0; //implement pseudo-tokens later
        $tx_data['note'] = $note;
        
        $insert_data = $tx_data;
        $insert_data['client_id'] = $oauth_client->id;
        // $insert_data['user_id'] = $userId;

        $insert = DB::table('provisional_tca_txs')->insertGetId($insert_data);
        if(!$insert){
            $output['error'] = 'Error saving provisional transaction';
            return Response::json($output, 500);
        }

        try {
            // fire an address balanced changed event
            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($tx_data['source']);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }

            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($tx_data['destination']);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }
        } catch (Exception $e) {
            EventLog::logError('AddressBalanceChanged.error', $e);
        }
        
        $tx_data['promise_id'] = $insert;
        
        //output result
        $output['result'] = true;
        $tx_data['ref'] = $set_ref;
        $output['tx'] = $tx_data;
        return Response::json($output);
    }

    public function getProvisionalTCATransaction($id) {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        $oauth_client = OAuthClientGuard::oauthClient();

        //get tx
        $query = Provisional::where('id', $id)->orWhere('txid', $id)->orWhere('fingerprint', $id);
        $provisional_transaction_model = $query->first();
        if(!$provisional_transaction_model){
            $output['error'] = 'Provisional tx not found';
            return Response::json($output, 404);
        }
        
        if($provisional_transaction_model->client_id != $oauth_client->id){
            $output['error'] = 'Cannot look at provisional tx that does not belong to you';
            return Response::json($output, 400);
        }
        
        $ref_data = $provisional_transaction_model->getRefData();
        if(isset($ref_data['user'])){ unset($ref_data['user']); }
        $transaction_array = $provisional_transaction_model->toArray();
        unset($transaction_array['client_id']);
        $transaction_array['promise_id'] = $transaction_array['id'];
        unset($transaction_array['id']);
        unset($transaction_array['user_id']);
        $transaction_array['ref'] = Provisional::joinRefData($ref_data);
        $output['tx'] = $transaction_array;
        $output['result'] = true;
        return Response::json($output);
    }      

    public function updateProvisionalTCATransaction($id) {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        $oauth_client = OAuthClientGuard::oauthClient();

        //get tx
        $query = DB::table('provisional_tca_txs')->where('id', $id)->orWhere('txid', $id)->orWhere('fingerprint', $id);
        $get = $query->first();
        if(!$get){
            $output['error'] = 'Provisional tx not found';
            return Response::json($output, 404);
        }
        
        if($get->client_id != $oauth_client->id){
            $output['error'] = 'Cannot update provisional tx that does not belong to you';
            return Response::json($output, 400);
        }
        
        //get data to update
        $update_data = array();
        if(isset($input['expiration'])){
            $time = time();
            if(!is_int($input['expiration'])){
                $input['expiration'] = strtotime($input['expiration']);
            }
            if($input['expiration'] <= $time){
                $output['error'] = 'New expiration must be sometime in the future';
                return Response::json($output, 400);
            }
            $update_data['expiration'] = $input['expiration'];
        }
                
        if(isset($input['quantity'])){
            //make sure they still have enough balance
            $quantity = intval($input['quantity']);
            if($quantity <= 0){
                $output['error'] = 'Invalid quantity, must be > 0';
                return Response::json($output, 400);
            }
            try{
                $total_promised = Provisional::getTotalPromised($get->source, $get->asset, $quantity, $get->id);
                $valid_balance = Provisional::checkValidPromisedAmount($get->source, $get->asset, $total_promised);
            }
            catch(Exception $e){
                $output['error'] = $e->getMessage();
                return Response::json($output, 500);
            }

            if(!$valid_balance['valid']){
                $output['error'] = 'Source address has insufficient asset balance to promise this transaction ('.round($total_promised/100000000,8).' '.$get->asset.' promised and only balance of '.round($valid_balance['balance']/100000000,8).')';
                return Response::json($output, 400);
            }
            $update_data['quantity'] = $quantity;            
        }
        
        $old_tx = false;
        if(isset($input['txid'])){
            $update_data['txid'] = $input['txid'];
            $old_tx = DB::table('provisional_tca_txs')
                        ->where('txid', $input['txid'])
                        ->where('client_id', $oauth_client->id)->first();
        }
        
        if(isset($input['fingerprint'])){
            $update_data['fingerprint'] = $input['fingerprint'];
            if(!$old_tx){
                $old_tx = DB::table('provisional_tca_txs')
                            ->where('fingerprint', $input['fingerprint'])
                            ->where('client_id', $oauth_client->id)->first();                
            }
        }
        
        if($old_tx AND $old_tx->id != $get->id){
            //edge case where manually submitting provisional tx,
            //then submitting transaction to network before updating manual promise may cause some overlap
            //assume previous tx is the real one (from xchain notification), delete it but keep quantity
            $update_data['quantity'] = $old_tx->quantity;
            DB::table('provisional_tca_tx')->where('id', $old_tx->id)->delete();
        }
        
        if(isset($input['ref'])){
            $update_data['ref'] = $input['ref'];
        }        
        
        if(isset($input['note'])){
            $update_data['note'] = trim(htmlentities($input['note']));
        }
        
        if(count($update_data) == 0){
            $output['error'] = 'Nothing to update';
            return Response::json($output, 400);
        }
        $update_data['updated_at'] = date('Y-m-d H:i:s');
        
        $update = DB::table('provisional_tca_txs')->where('id', $get->id)->update($update_data);
        
        if(!$update){
            $output['error'] = 'Error updating provisional transaction';
            return Response::json($output, 500);
        }

        try {
            // fire an address balanced changed event
            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($get->source);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }

            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($get->destination);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }
        } catch (Exception $e) {
            EventLog::logError('AddressBalanceChanged.error', $e);
        }
        
        return $this->getProvisionalTCATransaction($get->id);
    }              

    public function deleteProvisionalTCATransaction($id) {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        $oauth_client = OAuthClientGuard::oauthClient();

        //get tx
        $query = DB::table('provisional_tca_txs')->where('id', $id)->orWhere('txid', $id)->orWhere('fingerprint', $id);
        $get = $query->first();
        if(!$get){
            $output['error'] = 'Provisional tx not found';
            return Response::json($output, 404);
        }
        
        if($get->client_id != $oauth_client->id){
            $output['error'] = 'Cannot delete provisional tx that does not belong to you';
            return Response::json($output, 400);
        }
        
        //perform deletion
        $delete = $query->delete();
        if(!$delete){
            $output['error'] = 'Error deleting provisional tx';
            return Response::json($output, 500);
        }

        try {
            // fire an address balanced changed event
            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($get->source);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }

            $address_changed = app(AddressRepository::class)->findVerifiedByAddress($get->destination);
            if ($address_changed) { Event::fire(new AddressBalanceChanged($address_changed)); }
        } catch (Exception $e) {
            EventLog::logError('AddressBalanceChanged.error', $e);
        }

        
        $output['result'] = true;
        return Response::json($output);
    }

    public function getProvisionalTCATransactionList() {

        $output = array();
        $output['result'] = false;
        $input = Input::all();
        $oauth_client = OAuthClientGuard::oauthClient();
        
        
        $get_promises = Provisional::where('client_id', $oauth_client->id)->get();
        $output['list'] = array();
        if($get_promises){
            foreach($get_promises as $promise){
                $ref_data = $promise->getRefData();
                $promise = $promise->toArray();
                $promise['promise_id'] = $promise['id'];
                unset($promise['id']);
                unset($promise['client_id']);
                unset($promise['user_id']);
                if(isset($ref_data['user'])){
                    unset($ref_data['user']);
                }
                $promise['ref'] = Provisional::joinRefData($ref_data);
                $output['list'][] = $promise;
            }
            $output['result'] = true;
        }
        
        return Response::json($output);
    }
    
}
