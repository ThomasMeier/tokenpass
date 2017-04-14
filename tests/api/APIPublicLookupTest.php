<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

class APIPublicLookupTest extends TestCase {

    protected $use_database = true;




    public function testLookupUserByAddress() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', 'public'        => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'active_toggle' => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'verified'      => false,]);

        $user2 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user2, ['address' => '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']);

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // require authentication
        $response = $api_tester->testRequireAuth('GET', route('api.lookup.address', [
            'address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
        ]));

        // find user by address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']));
        PHPUnit::assertEquals($user1['username'], $response['result']['username']);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);

        // do not find user by private address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', [
            'address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v'
        ]), [], 404);
        // do not find user by inactive address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', [
            'address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD'
        ]), [], 404);
        // do not find user by unverified address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', [
            'address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL'
        ]), [], 404);

        // Multiple Details
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.lookup.addresses'), [
           'address_list' => ['1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs'],]
        );
        PHPUnit::assertCount(2, $response['users']);
        PHPUnit::assertEquals($user1['username'], $response['users']['1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']['username']);
        PHPUnit::assertEquals($user2['username'], $response['users']['1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']['username']);

        // Multiple Details skips inactive
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.lookup.addresses'), [
           'address_list' => ['1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs'],]
        );
        PHPUnit::assertCount(1, $response['users']);
        PHPUnit::assertEquals($user2['username'], $response['users']['1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']['username']);
    }



    public function testLookupAddressByUser() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', 'public'        => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'active_toggle' => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'verified'      => false,]);

        $user2 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user2, ['address' => '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs', 'public'        => false,]);

        $user3 = $user_helper->createRandomUser();
        $address_helper->createNewPseudoAddress($user3);

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // require authentication
        $response = $api_tester->testRequireAuth('GET', route('api.lookup.user', [
            'username' => $user1['username'],
        ]));

        // No user
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', [
            'username' => 'fake dude'
        ]), [], 404);
        PHPUnit::assertContains('User or addresses not found', $response['error']);

        // Get a user
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', [
            'username' => $user1['username']
        ]));
        PHPUnit::assertEquals($user1['username'], $response['result']['username']);
        PHPUnit::assertEquals($user1['email'], $response['result']['email']);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);

        // Get a user with no addresses (404 not found)
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', [
            'username' => $user2['username']
        ]), [], 404);
         PHPUnit::assertContains('User or addresses not found', $response['error']);

        // Get a user with only a pseudo addresses (404 not found)
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', [
            'username' => $user3['username']
        ]), [], 404);
         PHPUnit::assertContains('User or addresses not found', $response['error']);
   }

}
