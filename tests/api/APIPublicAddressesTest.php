<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

class APIPublicAddressesTest extends TestCase {

    protected $use_database = true;

    public function testGetPublicAddresses() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', 'public'        => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'active_toggle' => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'verified'      => false,]);
        $address_helper->createNewPseudoAddress($user1); // pseudo addresses are not included

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // require authentication
        $response = $api_tester->testRequireAuth('GET', route('api.tca.addresses', [
            'username' => $user1['username'],
        ]));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        // User is non existant
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', [
            'username' => 'IDontExist',
        ]), [], 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Username not found', $response['error']);

        // User has no addresses
        $user = $user_helper->createNewUser();
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', [
            'username' => $user->username ]
        ));
        PHPUnit::assertEmpty($response['result']);

        // User with addresses returns public, active and verified addresses only
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', [
            'username' => $user1['username'] ]
        ));
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertCount(2, $response['result']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result'][0]['address']);
    }

    public function testGetRefreshedPublicAddresses() {
        // mock address repository class
        $mock = Mockery::mock('Tokenpass\Repositories\AddressRepository')->makePartial();
        $mock->shouldReceive('updateUserBalances')->once()->andReturn(true);
        app()->instance('Tokenpass\Repositories\AddressRepository', $mock);


        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // test refresh
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', [
            'username' => $user1['username'], ]
        ), ['refresh' => 1]);

        Mockery::close();
    }


    public function testGetPublicAddressDetails() {
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

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // require authentication
        $response = app('OAuthClientAPITester')->testRequireAuth('GET', route('api.tca.address.public.details', [
            'username' => $user1['username'],
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'
        ]));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        // Address does not exist
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => $user1['username'],
            'address'  => '1NotRealAtAll'
        ]), [], 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Address details not found', $response['error']);

        // User does not exist
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => 'IDontExist',
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'
        ]), [], 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Username not found', $response['error']);

        // get user 1 address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => $user1['username'],
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'
        ]));
        PHPUnit::assertContains('btc', $response['result']['type']);
        PHPUnit::assertContains('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);
        PHPUnit::assertTrue($response['result']['verified']);

        // get user 2 address
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => $user2['username'],
            'address'  => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v'
        ]));
        PHPUnit::assertContains('btc', $response['result']['type']);
        PHPUnit::assertContains('1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', $response['result']['address']);
        PHPUnit::assertTrue($response['result']['verified']);

        // private address is not found
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => $user1['username'],
            'address'  => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD'
        ]), [], 404);

        // inactive address is not found
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.tca.address.public.details', [
            'username' => $user1['username'],
            'address'  => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL'
        ]), [], 404);

    }

}