<?php

namespace Tokenpass\Http\Controllers\Auth;

use Blockvis\Civic\Sip\AppConfig;
use Blockvis\Civic\Sip\Client;
use Illuminate\Support\Facades\Input;
use \Illuminate\Support\Facades\Session;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\User;

class CivicAuthController extends Controller
{

    function login() {
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

        if(User::where('civic_userID', $civicId)->where('civic_enabled', true)->exists()) {
            //User is already signed up
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
}