<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* APILogoutTest
*/
class APILogoutTest extends TestCase {

    protected $use_database = true;

    public function testAPILogout() {
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // require auth
        app('OauthUserAPITester')->expectUnauthenticatedResponse('GET', route('api.oauth.logout'));

        // verify db entries
        PHPUnit::assertCount(1, DB::table('oauth_access_tokens')->get());
        PHPUnit::assertCount(1, DB::table('oauth_sessions')->get());

        // Real result
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.oauth.logout'), []);
        PHPUnit::assertTrue($response['result']);

        // verify db entries were deleted
        PHPUnit::assertCount(0, DB::table('oauth_access_tokens')->get());
        PHPUnit::assertCount(0, DB::table('oauth_sessions')->get());
    }



}
