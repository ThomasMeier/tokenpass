<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

class APIMessengerTest extends TestCase {

    protected $use_database = true;


    public function testRequireScopeForGetMessengerPrivileges() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();

        $addresses = [
            $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']),
        ];

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $token = $oauth_helper->connectUserSession($user1, $oauth_client, []);
        $api_tester = app('OauthUserAPITester'); // ->setToken($user1_token);

        // no credentials
        $route_spec = ['api.messenger.token.privileges', 'FOOCOIN'];
        $result = $api_tester->expectUnauthenticatedResponse('GET', $route_spec);
    }

    public function testMessengerPrivilegesCanSend() {
        // mock tokenpass
        $mock = Mockery::mock('Tokenly\BvamApiClient\BVAMClient');
        $mock->shouldReceive('getAssetInfo')->withArgs(['MYTOKEN'])->once()->andReturn([
            'asset' => 'MYTOKEN',
            'assetInfo' => [
                'asset' => 'MYTOKEN',
                'issuer' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            ],
        ]);
        app()->instance('Tokenly\BvamApiClient\BVAMClient', $mock);

        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();

        $addresses = [
            $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']),
        ];
        $addresses[] = $address_helper->createNewAddress($user2, ['address' => '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        $route_spec = ['api.messenger.token.privileges', 'MYTOKEN'];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals(
            [
                'token'      => 'MYTOKEN',
                'canMessage' => true,
            ]
        , $result);

    }

    public function testMessengerPrivilegesCanNotSend() {
        // mock tokenpass
        $mock = Mockery::mock('Tokenly\BvamApiClient\BVAMClient');
        $mock->shouldReceive('getAssetInfo')->withArgs(['MYTOKEN'])->once()->andReturn([
            'asset' => 'MYTOKEN',
            'assetInfo' => [
                'asset' => 'MYTOKEN',
                'issuer' => '1AAAA9999xxxxxxxxxxxxxxxxxxxtA4f45',
            ],
        ]);
        app()->instance('Tokenly\BvamApiClient\BVAMClient', $mock);

        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();

        $addresses = [
            $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']),
        ];
        $addresses[] = $address_helper->createNewAddress($user2, ['address' => '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        $route_spec = ['api.messenger.token.privileges', 'MYTOKEN'];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals(
            [
                'token'      => 'MYTOKEN',
                'canMessage' => false,
            ]
        , $result);

    }

}