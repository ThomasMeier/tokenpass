<?php

namespace Tokenpass\Http\Controllers\Auth;

use Blockvis\Civic\Sip\AppConfig;
use Blockvis\Civic\Sip\Client;
use Illuminate\Support\Facades\Input;
use Tokenpass\Http\Controllers\Controller;

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


    }
}