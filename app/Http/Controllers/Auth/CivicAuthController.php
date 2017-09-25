<?php

namespace Tokenpass\Http\Controllers\Auth;

use Blockvis\Civic\Sip\AppConfig;
use Blockvis\Civic\Sip\Client;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use \Illuminate\Support\Facades\Session;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;

class CivicAuthController extends Controller
{
    use AuthenticatesUsers;

    function login(Request $request) {
        $input = Input::all();
        $jwtToken = $input['jwtToken'];
        // Configure Civic App credentials.
        $config = new AppConfig(
            'B14QGxljb',
            '86447b336aa77d680953c97a831b11f6',
            'ae7c5e68de7f489be0a48bc9527961f84d23e4fbe0d87d5bbc8adccb080d10b5'
        );
        // Instantiate Civic API client with config and HTTP client.
        $sipClient = new Client($config, new \GuzzleHttp\Client());
        // Exchange Civic authorization code for requested user data.
        $userData = $sipClient->exchangeToken($jwtToken);
        $civicId = $userData->userId();

        $email = $userData->items()[0]->value();

        $user = Auth::user();
        if($user) {
            //User is already logged in
            $user->civic_userID = $civicId;
            $user->civic_enabled = 1;
            $user->save();
            return redirect()->back();
        } elseif(User::where('civic_userID', $civicId)->where('civic_enabled', 1)->exists()) {
            $user = User::where('civic_userID', $civicId)->first();
            try {
                if(Address::checkUser2FAEnabled($user)) {
                    Session::set('user', $user);
                    return redirect()->action('Auth\AuthLoginController@getSignRequirement');
                }
            } catch(Exception $e) {

            }

            Auth::login($user);

            $this->authenticated($request, $user);
            return redirect('/dashboard');

        } else {
            $random_password = bin2hex(random_bytes(16));
            Session::set('civic_user_password', $random_password);
            Session::set('civic_user_email', $email);
            Session::set('civic_user_id', $civicId);
            Session::flash('message', 'Please fill the fields below to complete registration!');
            Session::flash('message-class', 'alert-success');
            return redirect('/auth/civic_registration');
        }
    }

    function finalizeRegistration() {
        $email = Session::get('civic_user_email');
        return view('auth.civic_registration', array('civic_email' => $email));
    }

    function disconnectFromCivic() {
        $user = Auth::user();

        $user->civic_userID = NULL;
        $user->civic_enabled = 0;
        $user->save();
        return redirect('/auth/update');
    }
}