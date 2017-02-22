<?php

use Illuminate\Support\Facades\Log;
use Tokenpass\Models\User;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;

/*
* TCAMessengerHelper
*/
class TCAMessengerHelper
{

    public function mockTCAMessengerAuth() {
        // mock TCAMessengerAuth
        $tca_messenger_auth_mock = Mockery::mock(TCAMessengerAuth::class);
        $tca_messenger_auth_mock->makePartial();
        $tca_messenger_auth_mock->shouldReceive('grant');
        $tca_messenger_auth_mock->shouldReceive('revoke');
        $tca_messenger_auth_mock->tokenpass_auth_key = 'tokenpass_auth_key_TEST';
        app()->instance(TCAMessengerAuth::class, $tca_messenger_auth_mock);
        return $tca_messenger_auth_mock;
    }


}