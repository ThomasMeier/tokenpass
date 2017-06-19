<?php
namespace Tokenpass\Http\Controllers\API;
use DB, Exception, Response, Input;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Log;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Repositories\AddressRepository;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;

class AddressesAPIController extends Controller
{

    use DispatchesJobs;

    public function __construct(OAuthClientRepository $oauth_client_repository, AddressRepository $address_repository, ClientConnectionRepository $client_connection_repository, UserRepository $user_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;
        $this->user_repository              = $user_repository;
        $this->address_repository           = $address_repository;
    }


    public function getPrivateAddresses()
    {
        $input = Input::all();
        $force_refresh = isset($input['refresh']) ? !!$input['refresh'] : false;

        $user = OAuthGuard::user();
        $priv_scope = OAuthGuard::hasScope('private-address');
        $manage_scope = OAuthGuard::hasScope('manage-address');

        if ($priv_scope) {
            $use_public = null;
        }
        else{
            $use_public = 1;
        }
        if($manage_scope){
            $and_active = null;
            $and_verified = false;
            $use_public = null;
        }
        else {
            $and_active = 1;
            $and_verified = 1;
        }

        if ($force_refresh) {
            $this->address_repository->updateUserBalances($user->id);
        }

        return $this->buildAddressesListResponse($user, $use_public, $and_active, $and_verified);
    }


    public function getPublicAddresses($username)
    {
        // resolve force_refresh
        // check the GET query parameter for refresh
        $input = Input::all();
        $force_refresh = (isset($input['refresh']) ? !!$input['refresh'] : false);

        // find the user by username
        $user = User::where('username', $username)->orWhere('slug', $username)->first();
        if(!$user) {
            $output['result'] = false;
            $output['error'] = 'Username not found';
            return Response::json($output, 404);
        }

        $use_public = 1;
        $and_active = 1;
        $and_verified = 1;

        if ($force_refresh) {
            $this->address_repository->updateUserBalances($user->id);
        }
        
        return $this->buildAddressesListResponse($user, $use_public, $and_active, $and_verified);
    }
        

    public function getPublicAddressDetails($username, $address)
    {
        // lookup the username
        $address_owner = User::where('username', $username)->orWhere('slug', $username)->first();
        if (!$address_owner) {
            $http_code = 404;
            $output['result'] = false;
            $output['error'] = 'Username not found';
            return Response::json($output, 404);
        }

        // lookup the address
        $address = Address::where('user_id', $address_owner['id'])->where('address', $address)->first();

        $address_is_valid = true;
        // not found
        if (!$address) { $address_is_valid = false; }
        // private
        if ($address_is_valid AND !$address->public) { $address_is_valid = false; }
        // inactive
        if ($address_is_valid AND !$address->active_toggle) { $address_is_valid = false; }
        // verified
        if ($address_is_valid AND !$address->verified) { $address_is_valid = false; }
        
        if (!$address_is_valid) {
            $output['error'] = 'Address details not found';
            $output['result'] = false;
            return Response::json($output, 404);
        }

        // return the address
        return $this->buildAddressObjectResponse($address);
    }

    public function getPrivateAddressDetails($address) {
        $user       = OAuthGuard::user();
        $priv_scope = OAuthGuard::hasScope('private-address');
        $manage_scope = OAuthGuard::hasScope('manage-address');

        // lookup the address
        $address = Address::where('user_id', $user['id'])->where('address', $address)->first();

        $address_is_valid = true;
        // not found
        if (!$address) { $address_is_valid = false; }
        // private
        if ($address_is_valid AND !$priv_scope AND !$manage_scope AND !$address->public) { $address_is_valid = false; }
        // inactive or unverified
        if ($address_is_valid AND !$manage_scope AND (!$address->active_toggle OR !$address->verified)) { $address_is_valid = false; }

        if (!$address_is_valid) {
            $output['error'] = 'Address details not found';
            $output['result'] = false;
            return Response::json($output, 404);
        }

        // return the address
        return $this->buildAddressObjectResponse($address, true);
    }


    
    public function registerAddress(Request $request)
    {
        $output = array();
        $input = Input::all();
        //check if a valid application client_id

        $this->validate($request, [
            'type'    => 'sometimes|in:btc,bitcoin',
            'address' => 'required|bitcoin',
            'label'   => 'sometimes|max:255',
            'public'  => 'sometimes|boolean',
            'active'  => 'sometimes|boolean',
        ]);

        $valid_client = false;
        $user = OAuthGuard::user();
        
        $type = 'btc';
        $address = trim($input['address']);
        
        $label = '';
        if(isset($input['label'])){
            $label = trim(htmlentities($input['label']));
        }
        
        $public = 0;
        if(isset($input['public']) AND intval($input['public']) == 1){
            $public = 1;
        }
        
        $active = 1;
        if(isset($input['active']) AND intval($input['active']) == 0){
            $active = 0;
        }
        
        $address_model = Address::where('user_id', $user->id)->where('address', $address)->first();
        if($address_model){
            $output['error'] = 'Address already registered';
            $output['result'] = false;
            return Response::json($output, 400);    
        }
        
        $new = app('Tokenpass\Repositories\AddressRepository')->create([
            'user_id'       => $user->id,
            'type'          => $type,
            'address'       => $address,
            'label'         => $label,
            'public'        => $public,
            'active_toggle' => $active,
            'from_api'      => true,
        ]);
        
        if(!$new){
            $output['error'] = 'Error registering address';
            $output['result'] = false;
            return Response::json($output, 500);
        }

        //Allow verifying ownership by payment
        $new->setUpPayToVerifyMethod();


        $result = [];
        $result['type']        = $type;
        $result['address']     = $address;
        $result['label']       = $label;
        $result['public']      = $public;
        $result['active']      = $active;
        $result['verify_code'] = $this->regenerateAddressSecureCode($address);
        $result['verify_address'] = $new->verify_address;
        $output['result']      = $result;

        return Response::json($output);
    }
    
    public function editAddress(Request $request, $address)
    {
        $output = array();
        $input = Input::all();

        $this->validate($request, [
            'label'   => 'sometimes|max:255',
            'public'  => 'sometimes|boolean',
            'active'  => 'sometimes|boolean',
        ]);

        $user = OAuthGuard::user();

        $address_model = Address::where('user_id', $user->id)->where('address', $address)->first();
        if(!$address_model){
            $output['error'] = 'Address not found';
            $output['result'] = false;
            return Response::json($output, 404);
        }   

        if ($address_model['pseudo']) {
            $output['error'] = 'Unable to edit pseudo address';
            $output['result'] = false;
            return Response::json($output, 400);
        }

        
        if(isset($input['label'])){
            $address_model->label = trim(htmlentities($input['label']));
        }
        if(isset($input['public'])){
            $public = intval($input['public']);
            $address_model->public = $public;
        }
        if(isset($input['active'])){
            $active = intval($input['active']);
            $address_model->active_toggle = $active;
        }
        $save = $address_model->save();
        if(!$save){
            $output['error'] = 'Error updating address';
            $output['result'] = false;
            return Response::json($output, 500);
        }
        
        // return the address
        return $this->buildAddressObjectResponse($address_model, true);
    }
    
    public function deleteAddress($address)
    {
        $output = array();
        $input = Input::all();

        $user = OAuthGuard::user();

        $address_model = Address::where('user_id', $user->id)->where('address', $address)->first();
        if(!$address_model){
            $output['error'] = 'Address not found';
            $output['result'] = false;
            return Response::json($output, 404);
        }           
        
        $delete = $address_model->delete();
        if(!$delete){
            $output['error'] = 'Error deleting address';
            $output['result'] = false;
            return Response::json($output, 500);
        }
        
        $output['result'] = true;
        return Response::json($output);
    }
    
    
    public function verifyAddress(Request $request, $address)
    {
        $output = array();
        $input = Input::all();
        $user = OAuthGuard::user();

        $this->validate($request, [
            'signature' => 'required|max:1024',
        ]);

        $address_model = Address::where('user_id', $user->id)->where('address', $address)->first();
        if(!$address_model){
            $output['error'] = 'Address not found';
            $output['result'] = false;
            return Response::json($output, 404);
        }   
        
        if($address_model->verified == 1){
            $output['error'] = 'Address already verified';
            $output['result'] = true;
            return Response::json($output, 400);
        }
        
        $expected_verify_code = $this->fetchAddressSecureCode($address);
        
        $sig = Address::extractSignature($input['signature']);
        $xchain = app('Tokenly\XChainClient\Client');
        
        $verify_message = $xchain->verifyMessage($address_model->address, $sig, $expected_verify_code);
        $verified = false;
        if($verify_message AND $verify_message['result']){
            $verified = true;
        }
        
        if(!$verified){
            $output['error'] = 'Invalid verification signature!';
            $output['result'] = false;
            return Response::json($output, 400);
        }
        
        $address_model->verified = 1;
        $save = $address_model->save();
        if(!$save){
            $output['error'] = 'Error updating address';
            $output['result'] = false;
            return Response::json($output, 500);
        }

        // make sure to sync the new address with any xchain balances
        $address_model->syncWithXChain();
        
        $output['result'] = true;
        return Response::json($output);
    }
    


    
    // ------------------------------------------------------------------------

    // excludes pseudo addresses
    protected function buildAddressesListResponse(User $user, $use_public, $and_active, $and_verified) {
        $output = [];
        
        $address_list = Address::getAddressList($user->id, $use_public, $and_active, $and_verified);
        if(!$address_list OR count($address_list) == 0){
            $output['result'] = array();
        }
        else{
            $addresses_list = array();
            foreach($address_list as $address){
                if ($address->pseudo) {
                    continue;
                }

                $item = array('address' => $address->address, 'balances' => Address::getAddressBalances($address->id, true, true, true), 'public' => boolval($address->public), 'label' => $address->label);
                if(!$address->verified) {
                    $item['verify_address'] = $address->verify_address;
                }
                if($and_active == null){
                    $item['active'] = boolval($address->active_toggle);
                }
                if(!$and_verified){
                    $item['verified'] = boolval($address->verified);
                }
                $addresses_list[] = $item;
            }
            $output['result'] = $addresses_list;
        }

        $http_code = 200;
        return Response::json($output, $http_code);
    }
    
    protected function buildAddressObjectResponse(Address $address, $private=false, $result=[]) {
        $result['type'] = $address->type;
        $result['address'] = $address->address;
        $result['label'] = $address->label;
        $result['public'] = boolval($address->public);
        $result['active'] = boolval($address->active_toggle);
        $result['verified'] = boolval($address->verified);
        $result['balances'] = Address::getAddressBalances($address->id, true);
        if(!$result['verified'] AND $private) {
            $result['verify_code'] = $this->regenerateAddressSecureCode($address->address);
            Cache::put(hash('sha256', $address->address), $result['verify_code'], 600);
            $result['verify_address'] = $address->verify_address;
        }       
        $output['result'] = $result;
        
        return Response::json($output);
    }

    protected function regenerateAddressSecureCode($address) {
        $verify_code = Address::getSecureCodeGeneration();
        Cache::put(hash('sha256', $address), $verify_code, 600);
        return $verify_code;
    }

    protected function fetchAddressSecureCode($address) {
        return Cache::get(hash('sha256', $address));
    }


    
}
