<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

class APIPublicBalancesTest extends TestCase {

    protected $use_database = true;


    public function testRequireScopeForGetPublicAddressBalances() {
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
        $route_spec = 'api.tca.public.balances';
        $result = $api_tester->expectUnauthenticatedResponse('GET', $route_spec);
    }

    public function testLookupPublicBalances() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $user3 = $user_helper->createRandomUser();

        $addresses = [
            $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', 'public'        => false,]),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'active_toggle' => false,]),
            $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'verified'      => false,]),
        ];
        $addresses[] = $address_helper->createNewAddress($user2, ['address' => '1AAAA6666xxxxxxxxxxxxxxxxxxy1Yu7gs']);
        $addresses[] = $address_helper->createNewPseudoAddress($user2);
        $addresses[] = $address_helper->createNewAddress($user3, ['address' => '1AAAA7777xxxxxxxxxxxxxxxxxxy1JNRbm']);

        $address_helper->addBalancesToAddress([
            'ASSETONE' => 5,
            'TOKENTWO' => 10,
        ], $addresses[0]);
        $address_helper->addBalancesToAddress([
            'ASSETONE' => 6,
            'TOKENTWO' => 11,
        ], $addresses[1]);
        $address_helper->addBalancesToAddress([
            'ASSETONE' => 7,
            'TOKENTWO' => 12,
        ], $addresses[2]);
        $address_helper->addBalancesToAddress([
            'ASSETONE' => 8,
            'TOKENTWO' => 13,
        ], $addresses[3]);
        $address_helper->addBalancesToAddress([
            'ASSETONE' => 9,
            'TOKENTWO' => 14,
        ], $addresses[4]);
        $address_helper->addBalancesToAddress([
            'ASSETONE' => 10,
            'TOKENTWO' => 15,
        ], $addresses[5]);

        // user 3 
        $address_helper->addBalancesToAddress([
            'TOKENTHREE' => 100,
        ], $addresses[7]);


        // create a provisional loan from user 3 to user 2 pseudo address
        app('ProvisionalHelper')->lend($addresses[7], $addresses[6], 35, 'TOKENTHREE');

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client, ['tca']);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        $route_spec = 'api.tca.public.balances';
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals(
            [
                0 => [
                    'asset'      => 'ASSETONE',
                    'name'       => 'ASSETONE',
                    'balance'    => 11, // 5+6
                    'balanceSat' => '1100000000',
                ],
                1 => [
                    'asset'      => 'TOKENTWO',
                    'name'       => 'TOKENTWO',
                    'balance'    => 21, // 10+11
                    'balanceSat' => '2100000000',
                ],
            ]
        , $result);

        // user two
        $user2_token = $oauth_helper->connectUserSession($user2, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user2_token);
        $route_spec = 'api.tca.public.balances';
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals(
            [
                0 => [
                    'asset'      => 'ASSETONE',
                    'name'       => 'ASSETONE',
                    'balance'    => 10,
                    'balanceSat' => '1000000000',
                ],
                1 => [
                    'asset'      => 'TOKENTWO',
                    'name'       => 'TOKENTWO',
                    'balance'    => 15,
                    'balanceSat' => '1500000000',
                ],
                2 => [
                    'asset'      => 'TOKENTHREE',
                    'name'       => 'TOKENTHREE',
                    'balance'    => 35,
                    'balanceSat' => '3500000000',
                ],
            ]
        , $result, json_encode($result, 192));


        // user three
        $user3_token = $oauth_helper->connectUserSession($user3, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user3_token);
        $route_spec = 'api.tca.public.balances';
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals(
            [
                0 => [
                    'asset'      => 'TOKENTHREE',
                    'name'       => 'TOKENTHREE',
                    'balance'    => 65,
                    'balanceSat' => '6500000000',
                ],
            ]
        , $result, json_encode($result, 192));



    }

}