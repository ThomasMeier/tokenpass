<?php

namespace Tokenpass\Http\Controllers\Auth;

use Illuminate\Support\Facades\Input;
use Tokenpass\Civic\Client;
use Tokenpass\Http\Controllers\Controller;

class CivicAuthController extends Controller
{

    function login() {
        $input = Input::all();
        $jwtToken = $input['jwtToken'];

        $civic_client = new Client(
            'S1WxqN3Y-',
            '044a7405965f7328467213fa888c08ddd0b4339a5153aea47464da6597f68dfabbb8e513df33695b82da3afd750c640606ec476c95f410cae5e1408d9612f1ec85',
            '8dd4a588a07964cdbcda03f0616fdd51a036dc96f647e9880ab90ee92f8c6c8776db2ec7b071f8f5299f133fdb744d822843e0d0032e9384c64d29506335a2f6'
        );

        $civic_client->exchangeCode($jwtToken);
    }
}