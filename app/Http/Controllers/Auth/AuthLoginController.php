<?php

namespace Tokenpass\Http\Controllers\Auth;

use Exception;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Tokenpass\Http\Controllers\Auth\Base\BaseAuthController;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Util\BitcoinUtil;


class AuthLoginController extends BaseAuthController
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesUsers;
    use DispatchesJobs;

    public function username()
    {
        return $this->username;
    }

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $user_repository)
    {
        $this->user_repository = $user_repository;

        $this->middleware('guest', ['except' => ['logout', 'getSignRequirement', 'setSigned']]);
        $this->middleware('auth', ['only' => []]);

    }


    public function postLogin(Request $request, UserRepository $user_repository) {
        
        if(Input::get('signature') AND Input::get('msg_hash')){
            $request->request->set('signed_message', Input::get('signature'));
            $request->request->set('msg_hash', Input::get('msg_hash'));
            return $this->postBitcoinLogin($request);
        }        
        
        $this->validate($request, [
            $this->username() => 'required', 'password' => 'required',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        $credentials = $this->credentials($request);

        $user = DB::table('users')->where('users.username', '=', $credentials['username'])->first();
        
        $check_pass=  false;
        if($user){
            $check_pass = Hash::check($credentials['password'], $user->password);
        }
        if(!$check_pass){
             return $this->sendFailedLoginResponse($request);
        }        

        try {
            if(Address::checkUser2FAEnabled($user)) {
                Session::set('user', $user);
                return redirect()->action('Auth\AuthLoginController@getSignRequirement');
            }
        } catch(Exception $e) {}

        list($login_error, $was_logged_in) = $this->performLoginLogic($credentials, $request->has('remember'));

        if ($was_logged_in) {
            $this->authenticated($request, $user);
            return $this->sendLoginResponse($request);
        }

        // throttle
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

        // ------------------------------------------------------------------------
    
    public function performLoginLogic($credentials, $remember) {
        $login_error = null;
        $second_time = false;

        // try authenticating with our local database
        if (Auth::attempt($credentials, $remember)) {
            
            $user = Auth::user();
            $session_id = \Session::getId();
            if ($user AND $session_id) {
                UserMeta::setMeta($user->id, 'session_id', $session_id);
            }
            
            return [null, true];
        }

        if ($login_error === null) { $login_error = $this->getFailedLoginMessage(); }

        return [$login_error, false];
    }

    // ------------------------------------------------------------------------
    
    



// public function toggleSecondFactor()
// {
//     $output = array('result' => false);
//     $response_code = 200;
//     $total_addresses = Address::getAddressList($this->user->id, null,1,1,1);

//     $second_factor_addresses = [];
//     foreach ($total_addresses as $address) {
//         if ($address->second_factor_toggle) {
//             array_push($second_factor_addresses, $address);
//         }
//     }

//     if (empty($second_factor_addresses)) {
//         $output['error'] = 'Please allow at minimum one address before switching on Second Factor Authentication';
//         $response_code = 400;
//     }

//     $input = Input::all();
//     if(!isset($input['toggle'])){
//         $output['error'] = 'Toggle option required';
//         $response_code = 400;
//     }
//     else{
//         $toggle_val = $input['toggle'];
//         if($toggle_val == 'true' OR $toggle_val === true){
//             $toggle_val = 1;
//         }
//         else{
//             $toggle_val = 0;
//         }
//         $get->second_factor_toggle = $toggle_val;
//         $save = $get->save();
//         if(!$save){
//             $output['error'] = 'Error updating address';
//             $response_code = 500;
//         }
//         else{
//             $output['result'] = true;
//         }
//     }

//     return Response::json($output, $response_code);
// }


public function getSignRequirement(Request $request, $user = null) {
    if (session()->has('user')) {
        $user = session()->get('user');
        $request->session()->reflash();
    } else {
        $user = Auth::user();
    }
    if(!$user){
        return redirect('auth/login');
    }
    $secondauth_enabled = Address::checkUser2FAEnabled($user);
    if(!$secondauth_enabled){
        return redirect('auth/login');
    }
    $sigval = Address::getUserVerificationCode($user, 'simple');
    $msg_hash = hash('sha256', $sigval['user_meta']);
    Cache::put($msg_hash, $user->id, 600);    
    return view('auth.sign', ['sigval' => $sigval['user_meta'], 'redirect' => $request['redirect'], 'msg_hash' => $msg_hash]);
}

public function setSigned(Request $request) {

    if(Input::get('signature') AND Input::get('msg_hash')){
        //click-to-sign functionality, look for session that contains this hash
        $sig = Input::get('signature');
        $input_msg_hash = Input::get('msg_hash');
        $user_id = Cache::get($input_msg_hash);
        $sesh_user = User::find($user_id);
        if($sesh_user){
            $sigval = Address::getUserVerificationCode($sesh_user, 'simple');
            $sigval = $sigval['user_meta'];
            $msg_hash = hash('sha256', $sigval);
            if($msg_hash != $input_msg_hash){
                Log::error('Hash value does not match session ('.$input_msg_hash.') - '.$sigval);
                return response()->json(array('error' => 'Hash value does not match session ('.$input_msg_hash.') - '.$sigval), 400);
            }
            //save submitted signature, process in main browser window
            Cache::put($msg_hash.'_sig', $sig, 600);
            return response()->json(array('result' => true));            
        }
        else{
            Log::error('User not found ('.$get_sesh->user_id.')');
            return response()->json(array('error' => 'User not found'), 400);
        }
    }
    
    if (session()->has('user')) {
        $user = session()->get('user');
    } else {
        $user = Auth::user();
    }

    if(!$user){
        return redirect('auth/login');
    }

    Session::set('user', null);

    //check if they actually have 2fa enabled
    $secondauth_enabled = Address::checkUser2FAEnabled($user);
    if(!$secondauth_enabled){
        return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
    }    

    $sigval = Address::getUserVerificationCode($user, 'simple');
    $sig = Address::extractSignature($request->request->get('signed_message'));
    try {
        $address = BitcoinUtil::deriveAddressFromSignature($sig, $sigval['user_meta']);
    } catch(Exception $e) {
        return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
    }
    
    //check if this address belongs to the user and they have 2FA enabled
    $get_address = Address::where('address', $address)->first();
    if(!$get_address OR $get_address->user_id != $user->id OR $get_address->second_factor_toggle == 0){
       return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
    }

    //verify signed message on xchain
    $verify = $this->verifySignature(['address' => $address, 'sig' => $sig, 'sigval' =>  $sigval['user_meta']]);
    if($verify) {
        UserMeta::setMeta($user->id,'sign_auth',$sigval['user_meta'],0,0,'signed');
        if (empty($request['redirect'])) {
            Auth::loginUsingId($user->id);
            $this->authenticated($request, $user);
            return $this->sendLoginResponse($request);
        }
        return redirect(urldecode($request['redirect']));
    } else {
        return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
    }
}

public function getLogin(Request $request){
    // Generate message for signing and flash for POST results
    $sigval = Address::getSecureCodeGeneration();
    Session::put('sigval', $sigval);
    $msg_hash = hash('sha256', $sigval);
    Cache::put($msg_hash, Session::getId(), 600);
    return view('auth.login', ['sigval' => $sigval, 'msg_hash' => $msg_hash]);
}

public function getBitcoinLogin(Request $request) {
    //page depreciated
    return redirect()->route('auth.login');
    /*
    // Generate message for signing and flash for POST results
    if(Input::get('signature')){
		$request->request->set('signed_message', Input::get('signature'));
		return $this->postBitcoinLogin($request);
	}
    $sigval = Address::getSecureCodeGeneration();
    Session::flash('sigval', $sigval);
    return view('auth.bitcoin', ['sigval' => $sigval]);
    */
}

public function postBitcoinLogin(Request $request) {
    
    $sig = Address::extractSignature($request->request->get('signed_message'));
    $input_msg_hash = $request->request->get('msg_hash');
    $msg_hash = null;
    if($input_msg_hash != null){
        //click-to-sign functionality, look for session that contains this hash
        $sesh_id = Cache::get($input_msg_hash);
        $get_sesh = false;
        if($sesh_id){
            $get_sesh = DB::table('sessions')->where('id', $sesh_id)->first();
        }
        if(!$get_sesh){
            Log::error('Session not found ('.$sesh_id.')');
            return response()->json(array('error' => 'Session not found'), 400);
        }
        $sesh_data = unserialize(base64_decode($get_sesh->payload));
        $sigval = $sesh_data['sigval'];
        $msg_hash = hash('sha256', $sigval);
        if($msg_hash != $input_msg_hash){
            Log::error('Hash value does not match session ('.$input_msg_hash.') - '.$sigval);
            return response()->json(array('error' => 'Hash value does not match session ('.$input_msg_hash.') - '.$sigval), 400);
        }
        //save submitted signature, process in main browser window
        Cache::put($msg_hash.'_sig', $sig, 600);
        return response()->json(array('result' => true));
    }
    
    $sigval = Session::get('sigval');

    if($sigval == null){
        Log::error('Sigval is null');
		return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
	}
    
    try {
        $address = BitcoinUtil::deriveAddressFromSignature($sig, $sigval);
    } catch(Exception $e) {
        Log::error('Error deriving address from signature '.$sig.' - '.$sigval);
        return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
    }

    $data = [
        'sigval'  => $sigval,
        'address' => $address,
        'sig'     => $sig];

    if($this->verifySignature($data)) {
        try {
            $result = User::getByVerifiedAddress($address);
        } catch(Exception $e) {
            Log::error('Valid signature but no matching address found');
            return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()
            ]);
        }
    }
    if(isset($result) AND $result) {
        try {
			$user = User::find($result->user_id);
            if(Address::checkUser2FAEnabled($user)) {
                Session::flash('user', $user);
                return redirect()->action('Auth\AuthLoginController@getSignRequirement');
            }
        } catch(Exception $e) {}		
		
        try {
            Auth::loginUsingId($result->user_id);
        } catch (Exception $e)
        {
            return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()]);
        }
        
        $this->authenticated($request, $user);

        return $this->sendLoginResponse($request);
    } else {
        return redirect()->route('auth.login')->withErrors([$this->getFailedLoginMessage()
        ]);
    }
}

public function checkForLoginSignature(Request $request)
{
    if(Input::get('2fa')){
        if (session()->has('user')) {
            $user = session()->get('user');
        } else {
            $user = Auth::user();
        }
        if(!$user){
            return response()->json(array('error' => 'Not logged in'), 400);
        }
        $sigval = Address::getUserVerificationCode($user, 'simple');
        $sigval = $sigval['user_meta'];
    }
    else{
        $sigval = Session::get('sigval');
    }
    
    $msg_hash = hash('sha256', $sigval);
    $get = Cache::get($msg_hash.'_sig');
    return response()->json(array('signature' => $get));
}


protected function verifySignature($data) {
    $sig = Address::extractSignature($data['sig']);
    $xchain = app('Tokenly\XChainClient\Client');

    $verify_message = $xchain->verifyMessage($data['address'], $sig, $data['sigval']);
    if($verify_message AND $verify_message['result']){
        return true;
    } else {
        return false;
    }
}





/**
 * Get the failed login message.
 *
 * @return string
 */
protected function getGenericFailedMessage()
{
    return Lang::has('auth.generic.fail')
        ? Lang::get('auth.generic.fail')
        : 'There has been an error, please check your input.';
}

/**
 * Get the failed login message.
 *
 * @return string
 */
protected function getFailedLoginMessage()
{
    return Lang::has('auth.failed')
            ? Lang::get('auth.failed')
            : 'These credentials do not match our records.';
}


}
