<?php

use Illuminate\Support\Facades\Event;
use PHPUnit_Framework_Assert as PHPUnit;

/*
* APIPerksTest
*/
class APIPerksTest extends TestCase {

    protected $use_database = true;

    public function testPerksAPI() {
        $token_chat_helper = app('TokenChatHelper');
        $token_chat = $token_chat_helper->createNewTokenChat();

        $api_tester = app('APITestHelper');

        $response = $api_tester->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.token-perks', ['token' => 'MYCOIN']), []);
        PHPUnit::assertEquals(1, $response['chatCount']);
        PHPUnit::assertEquals('MYCOIN', $response['token']);

        $response = $api_tester->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.token-perks', ['token' => 'DOESNOTEXIST']), []);
        PHPUnit::assertEquals(0, $response['chatCount']);
        PHPUnit::assertEquals('DOESNOTEXIST', $response['token']);

        $response = $api_tester->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.token-perks', ['token' => 'AAA']), [], 422);
        PHPUnit::assertEquals('Invalid token name', $response['message']);
    }
    
}
