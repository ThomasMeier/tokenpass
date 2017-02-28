<?php

use Illuminate\Support\Facades\Log;
use Tokenpass\Models\User;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;

/*
* TCAMessengerHelper
*/
class TCAMessengerHelper
{

    // used when not capturing expectations
    public function mockAll() {
        $this->mockTCAMessengerAuth();
        $this->mockTCAMessengerActions()->shouldReceive('_publish');
    }

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

    public function mockTCAMessengerActions() {
        // mock TCAMessengerAuth
        $tca_messenger_actions_mock = Mockery::mock(TCAMessengerActions::class);
        $tca_messenger_actions_mock->makePartial();
        app()->instance(TCAMessengerActions::class, $tca_messenger_actions_mock);
        return $tca_messenger_actions_mock;
    }

    public function debugPublish($tca_messenger_actions_mock) {
        $tca_messenger_actions_mock->shouldReceive('_publish')->andReturnUsing(function() {
            Log::debug("_publish ".json_encode(func_get_args(), 192));
        });
        return $tca_messenger_actions_mock;
    }


}