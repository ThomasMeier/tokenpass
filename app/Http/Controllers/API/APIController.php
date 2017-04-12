<?php
namespace Tokenpass\Http\Controllers\API;
use DB, Exception, Response, Input, Hash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Commands\SendUserConfirmationEmail;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient as AuthClient;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\OAuth\Facade\OAuthGuard;
use Tokenpass\Providers\CMSAuth\CMSAccountLoader;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Util\BitcoinUtil;

class APIController extends Controller
{

    use DispatchesJobs;

    public function __construct(OAuthClientRepository $oauth_client_repository, ClientConnectionRepository $client_connection_repository, UserRepository $user_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;  
        $this->user_repository = $user_repository;
    }


    public function checkSignRequirement($username) {
        $output = array();
        $input = Input::all();

        $user = User::where('username', $username)->orWhere('slug', $username)->first();
        if(!$user) {
            $output['result'] = false;
            $output['error'] = 'Username not found';
            return Response::json($output, 404);
        }

        $details = Address::getUserVerificationCode($user);
        $output['result'] = $details['extra'];

        return Response::json($output);
    }

    public function setSignRequirement(Request $request) {
        $output = array();
        $input = Input::all();
        $user = OAuthGuard::user();

        $this->validate($request, [
            'signature' => 'required|max:1024',
        ]);

        // calculate address for signing.
        $verification = Address::getUserVerificationCode($user);

        $address = BitcoinUtil::deriveAddressFromSignature($input['signature'], $verification['user_meta']);
        if(!$address) {
            $output['result'] = false;
            $output['error'] = 'Signature derive function failed';
            return Response::json($output, 403);
        }

        //verify signed message on xchain
        $xchain = app('Tokenly\XChainClient\Client');
        try{
            $verify = $xchain->verifyMessage($address, $input['signature'], $verification['user_meta']);
        } catch(Exception $e) {
            $verify = false;
        }
        if(!$verify OR !isset($verify['result']) OR !$verify['result']){
            $output['error'] = 'Signature invalid';
            return Response::json($output, 400);
        }
        if($verify) {
            UserMeta::setMeta($user->id,'sign_auth',$verification['user_meta'],0,0,'signed');
            $output['result'] = 'Signed';
            return Response::json($output);
        }
    }

    
    public function requestOAuth()
    {
        $input = Input::all();
        $output = array();
        $error = false;
        
        if(!isset($input['state'])){
            $error = true;
            $output['error'] = 'State required';
        }
        
        if(!isset($input['client_id'])){
            $error = true;
            $output['error'] = 'Client ID required';
        }

        $client_id = $input['client_id'];
        $client = $this->oauth_client_repository->findById($client_id);
        if (!$client){ 
            $error = true;
            $output['error'] = "Unable to find oauth client for client ".$client_id;
        }               
        
        if(!isset($input['scope'])){
            $error = true;
            $output['error'] = 'Scope required';
        }
        $scope_param = Input::get('scope');
        $scopes = array();
        if($scope_param AND count($scopes) == 0){
            $scopes = explode(',', $scope_param);
        }       
        
        if(!isset($input['response_type']) OR $input['response_type'] != 'code'){
            $error = true;
            $output['error'] = 'Invalid response type';
        }   
        
        if(!isset($input['username']) OR trim($input['username']) == ''){
            $error = true;
            $output['error'] = 'Username required';
        }
        
        if(!isset($input['password']) OR trim($input['password']) == ''){
            $error = true;
            $output['error'] = 'Password required';
        }
        
        if($error){
            return Response::json($output);
        }       
        
        $user = User::where('username', $input['username'])->orWhere('slug', $input['username'])->first();
        if(!$user){
            $error = true;
            $output['error'] = 'Invalid credentials';
        }
        else{
            $checkPass = Hash::check($input['password'], $user->password);
            if(!$checkPass){
                $error = true;
                $output['error'] = 'Invalid credentials';
            }
        }
        
        if(!$error){
            $already_connected = $this->client_connection_repository->isUserConnectedToClient($user, $client);
            if(!$already_connected){    
                $grant_access = false;
                if(isset($input['grant_access']) AND intval($input['grant_access']) === 1){
                    $grant_access = true;
                }
                if(!$grant_access){
                    $error = true;
                    $output['error'] = 'Application denied access to account';
                }
            }   
        }   
        
        if(!$error){
            $code_params =  Authorizer::getAuthCodeRequestParams();
            $code_url = Authorizer::issueAuthCode('user', $user->id, $code_params);
            $parse = parse_str(parse_url($code_url)['query'], $parsed);
            $output['code'] = $parsed['code'];
            $output['state'] = $parsed['state'];            
            if(!$already_connected){
                $this->client_connection_repository->connectUserToClient($user, $client, $scopes);
            }
        }
        
        return Response::json($output);
    }
    
    public function getOAuthToken()
    {
        $output = array();
        try {
            $output = Authorizer::issueAccessToken();
        } catch (\Exception $e) {
            Log::error("Exception: ".get_class($e).' '.$e->getMessage());
            $output['error'] = 'Failed getting access token';
        }
        return Response::json($output);
    }
    
    public function registerAccount()
    {
        $input = Input::all();
        $output = array();
        $error = false;
        $output['result'] = false;
        
        if(!isset($input['client_id']) OR !AuthClient::find($input['client_id'])){
            $error = true;
            $output['error'] = 'Invalid API client ID';
        }

        if(!isset($input['username']) OR trim($input['username']) == ''){
            $error = true;
            $output['error'] = 'Username required';
        }
        
        if(!isset($input['password']) OR trim($input['password']) == ''){
            $error = true;
            $output['error'] = 'Password required';
        }
        
        if(!isset($input['email']) OR trim($input['email']) == ''){
            $error = true;
            $output['error'] = 'Email required';
        }
        
        if($error){
            return Response::json($output);
        }
        
        $data['username'] = $input['username'];
        $data['password'] = $input['password'];
        $data['email'] = $input['email'];
        $data['name'] = '';
        if(isset($input['name'])){
            $data['name'] = $input['name'];
        }   
        
        
        $find_user = User::where('email', $data['email'])->orWhere('username', $data['username'])->first();
        if($find_user){
            $error = true;
            if($find_user->username == $data['username']){
                $output['error'] = 'Username already taken';
            }
            else{
                $output['error'] = 'Email already taken';
            }
        }       
        
        if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
            $error = true;
            $output['error'] = 'Invalid email address';
        }
        
        if(!$error){
            try{
                $registered_user = $this->user_repository->create([
                        'name'     => $data['name'],
                        'username' => $data['username'],
                        'email'    => $data['email'],
                        'password' => $data['password'],
                    ]);

                app(TCAMessenger::class)->authorizeUser($registered_user);
            }
            catch(\Exception $e){
                $registered_user = false;
                $output['error'] = 'Error registering account';
                EventLog::logError('registerAccount.error', $e);
            }
            
            if($registered_user){
                $output['result'] = [
                    'id'       => $registered_user->uuid,
                    'username' => $registered_user->username,
                    'email'    => $registered_user->email,
                    'ecc_key'  => $registered_user->ecc_key,
                ];
                $this->dispatch(new SendUserConfirmationEmail($registered_user));
            }
        }
        
        return Response::json($output);
    }
    
    public function updateAccount()
    {
        $input = Input::all();
        $output = array();
        $error = false;
        $output['result'] = false;
        
        if(!isset($input['client_id']) OR !AuthClient::find($input['client_id'])){
            $error = true;
            $output['error'] = 'Invalid API client ID';
        }
        
        if(!isset($input['user_id'])){
            $error = true;
            $output['error'] = 'User ID required';
        }
        
        if(!isset($input['token'])){
            $error = true;
            $output['error'] = 'OAuth Access Token required';
        }
        
        if(!isset($input['current_password'])){
            $error = true;
            $output['error'] = 'Current password required';
        }
        
        if($error){
            return Response::json($output);
        }       
        
        $user = User::where('uuid', $input['user_id'])->first();
        
        $get_token = DB::table('oauth_access_tokens')->where('id', $input['token'])->first();
        $valid_access = false;
        if($get_token AND $user){
            $get_sesh = DB::table('oauth_sessions')->where('id', $get_token->session_id)->first();
            if($get_sesh AND $get_sesh->client_id == $input['client_id'] AND $get_sesh->owner_id == $user->id){
                $valid_access = true;
            }
        }
        if(!$valid_access){
            $output['error'] = 'Invalid access token, client ID or user ID';
            return Response::json($output); 
        }
        
        $check = Hash::check($input['current_password'], $user->password);
        if(!$check){
            $output['error'] = 'Invalid password';
            return Response::json($output); 
        }       
        
        $to_change = array();
        if(isset($input['name']) AND $input['name'] != $user->name){
            $to_change['name'] = trim($input['name']);
        }       
        if(isset($input['email']) AND trim($input['email']) != '' AND $input['email'] != $user->email){
            $to_change['email'] = $input['email'];
        }
        if(isset($input['password']) AND trim($input['password']) != ''){
            $to_change['password'] = $input['password'];
        }       
        
        if(count($to_change) == 0){
            $output['error'] = 'No changes to make';
            return Response::json($output); 
        }
        $changed = array_keys($to_change);
        foreach($to_change as $k => $v){
            switch($k){
                case 'name':
                    $user->name = $v;
                    break;
                case 'email':
                    $user->email = $v;
                    $this->dispatch(new SendUserConfirmationEmail($user));
                    break;
                case 'password':
                    $user->password = Hash::make($v);
                    break;
            }
        }
        
        $save = $user->save();
        if(!$save){
            $output['error'] = 'Error saving updated account information';
        }
        else{
            $output['result'] = 'success';
        }
        
        return Response::json($output);
    }
    
    public function invalidateOAuth()
    {
        $input = Input::all();
        $output = array();
        $output['result'] = false;

        $user = OAuthGuard::user();
        $session = OAuthGuard::session();
        $access_token = OAuthGuard::accessToken();

        // $get = User::getByOAuth($input['token']);
        $browser_sesh = UserMeta::getMeta($user['id'], 'session_id');
        if($browser_sesh){
            DB::table('sessions')->where('id', $browser_sesh)->delete();
        }       
        DB::table('oauth_access_tokens')->where('id', $access_token['id'])->delete();
        DB::table('oauth_sessions')->where('id', $session['id'])->delete();
        $output['result'] = true;
        return Response::json($output);
    }

    /**
     * This only verifies the user by login and password.  It does not confer any grants
     * @param  Request $request The HTTP Request
     * @return JsonResponse     The HTTP Response
     */
    public function loginWithUsernameAndPassword(Request $request) {
        $this->validate($request, [
            'client_id' => 'required',
            'username'  => 'required|max:255',
            'password'  => 'required|max:255',
        ]);

        // require a valid client_id
        $client_id = $request->input('client_id');
        $valid_client = AuthClient::find($client_id);
        if (!$valid_client) {
            $error = 'Invalid API client ID';
            return new JsonResponse(['message' => $error, 'errors' => [$error]], 403);
        }

        $credentials = $request->only(['username','password']);
        $auth_controller = app('Tokenpass\Http\Controllers\Auth\AuthLoginController');
        list($login_error, $was_logged_in) = $auth_controller->performLoginLogic($credentials, false);
        if ($was_logged_in) {
            $user = Auth::user();
            return new JsonResponse([
                'id'              => $user['uuid'],
                'name'            => $user['name'],
                'username'        => $user['username'],
                'email'           => $user['email'],
                'confirmed_email' => $user['confirmed_email'],
                'ecc_key'         => $user['ecc_key'],
            ], 200);
        }

        if (!$login_error) { $login_error = 'failed to login'; }
        return new JsonResponse(['message' => $login_error, 'errors' => [$login_error]], 422);
    }

    protected function buildFailedValidationResponse(Request $request, array $errors)
    {
        if (is_array($errors)) {
            $error_strings = [];
            foreach($errors as $error) {
                $error_strings = array_merge($error_strings, array_values($error));
            }
            $message = implode(" ", $error_strings);
            $errors = $error_strings;
        } else {
            $message = $errors;
            $errors = [$errors];
        }
        return new JsonResponse(['message' => $message, 'errors' => $errors], 422);
    }
    
    
    public function instantVerifyAddress($username)
    {
        $output = array();
        $output['result'] = false;
        
        //find user
        $user = User::where('username', $username)->orWhere('slug', $username)->first();
        if(!$user){
            $output['error'] = 'User not found'; 
            return Response::json($output, 404);
        }
        
        //check they included an address
        $verify_address = Input::get('address');
        if(!$verify_address OR trim($verify_address) == ''){
            $output['error'] = 'Address required'; 
            return Response::json($output, 400);
        }

        //get the message needed to verify and check inputs
        $verify_message = Address::getInstantVerifyMessage($user, false);
        $input_sig = Input::get('sig');
        if(Input::get('signature')){
            $input_sig = Input::get('signature');
        }
        $input_message = Input::get('msg');
        if(!$input_sig OR trim($input_sig) == ''){
            $output['error'] = 'sig required';
            return Response::json($output, 400);
        }

        if(!$input_message OR $input_message != $verify_message){
            $output['error'] = 'msg invalid';
            return Response::json($output, 400);
        }

        //verify address is already not in use
        $address = Input::get('address');
        $existing_addresses = Address::where('address', $address)->get();
            if (!empty($existing_addresses[0])) {
                $output['error'] = 'Address already exists';
                return Response::json($output, '400');
        }
        
        //verify signed message on xchain
        $xchain = app('Tokenly\XChainClient\Client');
        try{
            $verify = $xchain->verifyMessage($verify_address, $input_sig, $verify_message);
        }
        catch(Exception $e){
            $verify = false;
        }
        if(!$verify OR !isset($verify['result']) OR !$verify['result']){
            $output['error'] = 'signature invalid';
            return Response::json($output, 400);
        }
        
        //check to see if this address exists on their account
        $address = Address::where('user_id', $user->id)->where('address', $verify_address)->first();
        if(!$address){
            //register new address
            $address = app('Tokenpass\Repositories\AddressRepository')->create([
                'user_id'  => $user->id,
                'type'     => 'btc',
                'address'  => $verify_address,
                'verified' => 1,
            ]);
            $save = ($address ? true : false);
        }
        else{
            //verify existing address
            $save = app('Tokenpass\Repositories\AddressRepository')->update($address, [
                'verified' => true,
            ]);
        }
        if(!$save){
            $output['error'] = 'Error saving address';
            return Response::json($output, 500);
        }

        if ($address['verified']) {
            // make sure to sync the new address with any xchain balances
            $address->syncWithXChain();
        }

        
        UserMeta::setMeta($user->id, 'force_inventory_page_refresh', 1);
        UserMeta::setMeta($user->id, 'inventory_refresh_message', 'Address '.$address->address.' registered and verified!');
        UserMeta::clearMeta($user->id, 'instant_verify_message');
        $output['result'] = true;
        
        return Response::json($output);
    }
    
    // ------------------------------------------------------------------------
    
}
