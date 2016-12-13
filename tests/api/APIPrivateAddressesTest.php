<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* APIPrivateAddressesTest
*/
class APIPrivateAddressesTest extends TestCase {

    protected $use_database = true;

    // ------------------------------------------------------------------------
    // Private Addresses

    public function testGetPrivateAddresses() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // require authentication
        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertContains('No access token provided', $response['message']);

        // test user with no addresses
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertEmpty($response['result']);

        // add test user and addresses with 1 being private
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', 'public' => false,]);
        
        // get both the public and the private address
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertNotEmpty($response['result']);
        PHPUnit::assertCount(2, $response['result']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result'][0]['address']);
        PHPUnit::assertContains('1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', $response['result'][1]['address']);

        // a different user returns no addresses
        list($user2, $user2_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester_2 = app('OauthUserAPITester')->setToken($user2_token);
        $response = $api_tester_2->expectAuthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertEmpty($response['result']);
    }


    public function testGetPrivatePseudoAddresses() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // add test user and addresses with 1 being private
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', 'public' => false,]);
        $address_helper->createNewPseudoAddress($user1);
        
        // get both the public and the private address (but not the pseudo address)
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertNotEmpty($response['result']);
        PHPUnit::assertCount(2, $response['result']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result'][0]['address']);
        PHPUnit::assertContains('1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', $response['result'][1]['address']);
    }

    public function testGetPrivateAddressRequiresPrivateTCAScope() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test user and addresses with 1 being private
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession([], ['tca']);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', 'public' => false,]);

        // only return the public address
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.addresses'));
        PHPUnit::assertNotEmpty($response['result']);
        PHPUnit::assertCount(1, $response['result']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result'][0]['address']);
    }

    public function testRequiresOAuthTokenForGetPrivateAddressDetails() {
        $oauth_helper = app('OAuthClientHelper');

        $user = app('UserHelper')->getOrCreateSampleUser();
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);

        // random user
        $token = $oauth_helper->connectUserSession($user, $oauth_client);

        $api_tester = new OauthUserAPITester();
        $route_spec = ['api.tca.private.address.details', ['address' => '1foo']];

        // no credentials
        $result = $api_tester->expectUnauthenticatedResponse('GET', $route_spec);

        // just client_id (still fails because it requires a oauth_token)
        $result = $api_tester->expectUnauthenticatedResponse('GET', $route_spec, ['client_id' => $oauth_client['id']]);

        // good
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec, ['oauth_token' => $token], 404);
    }
    public function testRequiresTCAScopeForGetPrivateAddressDetails() {
        $oauth_helper = app('OAuthClientHelper');
        $address_helper = app('AddressHelper');

        $user = app('UserHelper')->getOrCreateSampleUser();
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        $address_helper->createNewAddress($user, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);

        // user with NO scopes
        $token = $oauth_helper->connectUserSession($user, $oauth_client, []);

        $api_tester = new OauthUserAPITester();
        $route_spec = ['api.tca.private.address.details', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']];

        // try bad scope
        $result = $api_tester->expectUnauthenticatedResponse('GET', $route_spec, ['oauth_token' => $token]);
        PHPUnit::assertContains('scopes are not authorized', $result['message']);
    }


    public function testGetPrivateAddressDetails() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);
        $address_helper->createNewAddress($user2, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'public' => false]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'active_toggle' => false]);

        // create an oauth-ed user and client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = new OauthUserAPITester($token);

        // Address does not exist
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.address.details', [
            'address'  => '1NotRealAtAll'
        ]), [], 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Address details not found', $response['error']);

        // get user 1 address
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.address.details', [
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'
        ]));
        PHPUnit::assertContains('btc', $response['result']['type']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);
        PHPUnit::assertTrue($response['result']['verified']);

        // does NOT get user 2 address
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.address.details', [
            'address'  => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v'
        ]), [], 404);
        PHPUnit::assertContains('Address details not found', $response['error']);

        // private address IS found
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.private.address.details', [
            'address'  => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD'
        ]));
        PHPUnit::assertContains('1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', $response['result']['address']);
        PHPUnit::assertTrue($response['result']['verified']);

    }

}
