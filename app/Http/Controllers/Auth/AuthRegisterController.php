<?php

namespace TKAccounts\Http\Controllers\Auth;

use Exception;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;
use ReCaptcha;
use TKAccounts\Commands\SendUserConfirmationEmail;
use TKAccounts\Http\Controllers\Auth\Base\BaseAuthController;
use TKAccounts\Models\Address;
use TKAccounts\Models\User;
use TKAccounts\Models\UserMeta;
use TKAccounts\Providers\CMSAuth\Util;
use TKAccounts\Repositories\UserRepository;
use Validator;


class AuthRegisterController extends BaseAuthController
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

    use RegistersUsers;
    use DispatchesJobs;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $user_repository)
    {
        $this->user_repository = $user_repository;

        $this->middleware('guest', ['except' => ['getUpdate','postUpdate',]]);
        $this->middleware('auth', ['only' => ['getUpdate', 'postUpdate']]);

    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postRegister(Request $request)
    {
        if (env('APP_ENV') != 'testing') {
            $captcha = $this->checkCaptcha($request);
            if (is_null($captcha)) {
                return redirect()->back()->withErrors([$this->getGenericFailedMessage()]);
            }
            if ($captcha->isSuccess() == false) {
                return redirect()->back()->withErrors([$this->getGenericFailedMessage()]);
            }
        }

        $register_vars = $request->all();
        $register_vars['slug'] = Util::slugify(isset($register_vars['username']) ? $register_vars['username'] : '');

        $validator = $this->validator($register_vars);

        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }

        // we can't create a new user with an existing LTB username
        $loader = app('TKAccounts\Providers\CMSAuth\CMSAccountLoader');
        if ($loader->usernameExists($register_vars['username'])) {
            $register_error = 'This username was found at LetsTalkBitcoin.com.  Please login with your existing credentials instead of creating a new account.';
            throw new HttpResponseException($this->buildFailedValidationResponse($request, ['username' => $register_error]));
        }


        $new_user = $this->create($request->all());
        Auth::login($new_user);

        // send the confirmation email
        $this->dispatch(new SendUserConfirmationEmail($new_user));

        // if we came from an authorization request
        //   then continue by redirecting the user to their original, intended request
        return redirect()->intended($this->redirectPath());
    }

    private function checkCaptcha($request) {
        $secret = env('RECAPTCHA');
        $response = null;
        $reCaptcha = new \ReCaptcha\ReCaptcha($secret);

        if ($request["g-recaptcha-response"]) {
            $response = $reCaptcha->verify(
                $request["g-recaptcha-response"],
                $_SERVER["REMOTE_ADDR"]
            );
        }

        return $response;
    }


        // ------------------------------------------------------------------------
    

    // ------------------------------------------------------------------------
    
    


    public function getUpdate(Request $request)
    {
        $current_user = Auth::user();

        $flashable = [];
        foreach ($current_user->updateableFields() as $field_name) {
            $flashable[$field_name] = Session::hasOldInput($field_name) ? Session::getOldInput($field_name) : $current_user[$field_name];
        }
        $request->getSession()->flashInput($flashable);


        return view('auth.update', ['model' => $current_user]);
    }

    public function postUpdate(Request $request, UserRepository $user_repository)
    {
        try {
                
            $current_user = Auth::user();

            $request_params = $request->all();

            // if email or username is not present, then throw an error
            if (!isset($request_params['email']) OR !strlen($request_params['email'])) { throw new InvalidArgumentException("Email is requred", 1); }

            $update_vars = $request_params;

            // if submitted email matches current user email, then don't validate it
            if ($request_params['email'] == $current_user['email']) {
                unset($update_vars['email']);
            }

            // validate
            $validator = $this->updateValidator($update_vars);
            if ($validator->fails()) {
                $this->throwValidationException(
                    $request, $validator
                );
            }

            // check existing password
            $password_matched = $current_user->passwordMatches($request_params['password']);
            if (!$password_matched) {
                $error_text = 'Please provide the correct password to make changes.';
                Log::debug("\$request->input()=".json_encode($request->input(), 192));
                throw new HttpResponseException($this->buildFailedValidationResponse($request, ['password' => $error_text]));
            }


            // if a new password is present, set the password variable
            unset($update_vars['password']);
            if (isset($update_vars['new_password']) AND strlen($update_vars['new_password'])) {
                $update_vars['password'] = $update_vars['new_password'];
                unset($update_vars['new_password']);
            }
            unset($update_vars['new_password']);

            // filter for only valid variables
            $field_names = array_keys($validator->getRules());
            $filtered_update_vars = [];
            foreach($field_names as $field_name) {
                if (isset($update_vars[$field_name])) {
                    $filtered_update_vars[$field_name] = $update_vars[$field_name];
                }
            }
            $update_vars = $filtered_update_vars;


            // update the user
            $user_repository->update($current_user, $update_vars);

            // if the email changed, send a confirmation email
            if (isset($update_vars['email']) AND strlen($update_vars['email']) AND $update_vars['email'] != $current_user['confirmed_email']) {
                Log::debug("\$update_vars['email']=".json_encode($update_vars['email'], 192));
                $this->dispatch(new SendUserConfirmationEmail($current_user));
            }
            
            Session::flash('message', 'Settings updated!');
            Session::flash('message-class', 'alert-success');
            return redirect('/auth/update');

        } catch (InvalidArgumentException $e) {
throw new HttpResponseException($this->buildFailedValidationResponse($request, [0 => $e->getMessage()]));
}

}
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



public function clickVerifyAddress($address)
{
    if(Input::get('signature') AND Input::get('msg_hash')){
        //click-to-sign functionality, look for session that contains this hash
        $sig = Input::get('signature');
        $input_msg_hash = Input::get('msg_hash');
        $user_id = Cache::get($input_msg_hash);
        $sesh_user = User::find($user_id);
        if($sesh_user){
            $sigval = Cache::get($input_msg_hash.'_msg');
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
    return response()->json(array('error' => 'Invalid request'), 400);
}



/**
 * Get a validator for an incoming registration request.
 *
 * @param  array  $data
 * @return \Illuminate\Contracts\Validation\Validator
 */
protected function validator(array $data)
{
    return Validator::make($data, [
        'name'     => 'max:255',
        'username' => 'required|max:255|unique:users',
        'slug'     => 'sometimes|max:255|unique:users',
        'email'    => 'required|email|max:255|unique:users',
        'password' => 'required|confirmed|min:6',
    ]);
}

/**
 * Get a validator for an incoming registration request.
 *
 * @param  array  $data
 * @return \Illuminate\Contracts\Validation\Validator
 */
protected function updateValidator(array $data)
{
    return Validator::make($data, [
        'name'         => 'max:255',
        // 'username'     => 'sometimes|max:255|unique:users',
        'email'        => 'sometimes|email|max:255|unique:users',
        'new_password' => 'sometimes|confirmed|min:6',
        'password'     => 'required',
        'second_factor'     => 'integer',
    ]);
}

/**
 * Create a new user instance after a valid registration.
 *
 * @param  array  $data
 * @return User
 */
protected function create(array $data)
{
    try {
        return $this->user_repository->create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);
    } catch (Exception $e) {
        throw $e;
    }

}


}
