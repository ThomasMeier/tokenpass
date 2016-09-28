<?php

use PHPUnit_Framework_Assert as PHPUnit;

/*
* APITCAControllerTest
*/
class APITCAControllerTest extends TestCase {

    const SATOSHI = 100000000;

    protected $use_database = true;

    public function testCheckTokenAccessAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // create a user
        $user = $user_helper->createNewUser();

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // create a new address
        $new_address = $address_helper->createNewAddress($user, [
            'type'     => 'BTC',
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);

        // add a new balance entry to the new address
        DB::Table('address_balances')->insert([
            'address_id' => $new_address->id,
            'asset'      => 'TOKENLY',
            'balance'    => 10 * self::SATOSHI,
            'updated_at' => time(),
        ]);
        DB::Table('address_balances')->insert([
            'address_id' => $new_address->id,
            'asset'      => 'LTBCOIN',
            'balance'    => 5000 * self::SATOSHI,
            'updated_at' => time(),
        ]);

        $route = route('api.tca.check', ['username' => $user['username']]);

        // require authentication
        $response = $api_tester->testRequireAuth('GET', $route);
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);


        // check 10 TOKENLY (should be true)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 10];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY (should be false)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 11];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY AND 5000 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5000];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 10 TOKENLY AND 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY OR 5001 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY OR 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 11, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);
    }

    public function testAddressBasedCheckTokenAccessAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // create a user
        $user = $user_helper->createNewUser();

        // setup api client
        $api_tester = app('OAuthClientAPITester');

        // set xchain mock balances
        $this->buildXChainMock()->setBalancesByAddress([
            '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j' => [
                'TOKENLY' =>   10,
                'LTBCOIN' => 5000,
            ],
        ]);
        $route = route('api.tca.check-address', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);


        // check 10 TOKENLY (should be true)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 10];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY (should be false)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 11];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY AND 5000 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5000];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 10 TOKENLY AND 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY OR 5001 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY OR 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 11, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);
    }

    // ------------------------------------------------------------------------
    
    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->mock_builder;
    }

}
