<?php

use PHPUnit_Framework_Assert as PHPUnit;

/*
* APIMessengerControllerTest
*/
class APIMessengerControllerTest extends TestCase {

    const SATOSHI = 100000000;

    protected $use_database = true;

    public function testCheckTokenAccessAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // create a user
        $user = $user_helper->createNewUser();

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        //$api_tester = app('OAuthClientAPITester')->be($oauth_client);

        $token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = new OauthUserAPITester($token);

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

        // check 10 TOKENLY (should be true)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 10];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY (should be false)
        $token = 'TOKENLY';
        $query_params = ['TOKENLY' => 11];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY AND 5000 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5000];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 10 TOKENLY AND 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
        PHPUnit::assertFalse($response['result']);

        // check 10 TOKENLY OR 5001 LTBCOIN (should be true)
        $query_params = ['TOKENLY' => 10, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        // check 11 TOKENLY OR 5001 LTBCOIN (should be false)
        $query_params = ['TOKENLY' => 11, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $response = $api_tester->expectAuthenticatedResponse('GET', $route, $query_params);
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


    public function testRequiresAuthForFindUsersByTCARules() {
        $api_tester = app('APITestHelper');
        $response = $api_tester->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.usersbytca'), [], 403);
    }

    public function testFindUsersByTCARules() {
        $user_helper    = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // create a user
        $users = [];
        $users[0] = $user_helper->createRandomUser();
        $users[1] = $user_helper->createRandomUser();
        $users[2] = $user_helper->createRandomUser();

        // setup api client
        $api_tester = app('APITestHelper');
        $api_tester->be($users[0]);

        // create new addresses
        $addresses = [];

        // user 0, Address 0
        $user_offset = 0; $address_offset = 0;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);
        $address_helper->addBalancesToAddress([
            'COINAAA' => 1,
            'COINBBB' => 2,
            'COINCCC' => 3,
        ], $addresses[$address_offset]);

        // user 0, Address 1
        $user_offset = 0; $address_offset = 1;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);
        $address_helper->addBalancesToAddress([
            'COINAAA'  => 1,
            'COINBBB'  => 1,
            'COINCCC'  => 1,
            'USERZERO' => 1,
        ], $addresses[$address_offset]);

        // user 1, Address 2
        $user_offset = 1; $address_offset = 2;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);
        $address_helper->addBalancesToAddress([
            'COINBBB' => 50,
            'COINCCC' => 50,
            'USERONE' => 1,
        ], $addresses[$address_offset]);

        // user 2, Address 3 (inactive)
        $user_offset = 2; $address_offset = 3;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
            'active_toggle' => 0,
        ]);
        $address_helper->addBalancesToAddress([
            'COINAAA' => 50,
            'COINBBB' => 50,
            'COINCCC' => 50,
            'USERTWO' => 1,
        ], $addresses[$address_offset]);

        // user 2, Address 4 (private)
        $user_offset = 2; $address_offset = 4;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 0,
        ]);
        $address_helper->addBalancesToAddress([
            'COINCCC' => 50,
        ], $addresses[$address_offset]);


        // check 1 COINAAA (should be user 0 only)
        $query_params = ['COINAAA' => 1];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(1, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);

        // check 2 COINAAA (should be user 0 only)
        $query_params = ['COINAAA' => 2];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(1, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);

        // check 3 COINAAA (should be none)
        $query_params = ['COINAAA' => 3];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(0, $response['count']);

        // check 1 COINBBB (should be user 0,1)
        $query_params = ['COINBBB' => 1];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(2, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);
        PHPUnit::assertEquals($users[1]['uuid'], $response['results'][1]['id']);

        // check 50 COINBBB (should be user 1)
        $query_params = ['COINBBB' => 50];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(1, $response['count']);
        PHPUnit::assertEquals($users[1]['uuid'], $response['results'][0]['id']);

        // check 50 COINCCC (should be user 1,2)
        $query_params = ['COINCCC' => 50];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(2, $response['count']);
        PHPUnit::assertEquals($users[1]['uuid'], $response['results'][0]['id']);
        PHPUnit::assertEquals($users[2]['uuid'], $response['results'][1]['id']);

        // check 1 COINCCC (should be user 1,2,3)
        $query_params = ['COINCCC' => 1];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(3, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);
        PHPUnit::assertEquals($users[1]['uuid'], $response['results'][1]['id']);
        PHPUnit::assertEquals($users[2]['uuid'], $response['results'][2]['id']);

        // loan 50 COINBBB from user 1 to user 0
        app('ProvisionalHelper')->lend($addresses[2], $addresses[0], 50, 'COINBBB');

        // check 50 COINBBB (should be user 0)
        $query_params = ['COINBBB' => 50];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(1, $response['count'], "Expected only {$users[0]['uuid']}.  Found ".json_encode($response, 192));
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);

        // complicated query
        $query_params = ['USERONE' => 1, 'USERZERO' => 1, 'stackop_1' => 'OR'];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(2, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);
        PHPUnit::assertEquals($users[1]['uuid'], $response['results'][1]['id']);

        // complicated query 2
        $query_params = ['USERONE' => 1, 'NOTEXISTS' => 1, 'USERZERO' => 1, 'stackop_1' => 'AND', 'stackop_2' => 'OR'];
        $response = $api_tester->callJSON('GET', route('api.tca.usersbytca'), $query_params);
        PHPUnit::assertEquals(1, $response['count']);
        PHPUnit::assertEquals($users[0]['uuid'], $response['results'][0]['id']);
    }


    public function testMessengerBroadcast() {
        $pubnub_mock      = $this->mockPubnub();
        $bvam_client_mock = $this->mockBVAMClient();
        $bvam_client_mock->shouldIgnoreMissing();

        $user_helper    = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // create sending user
        $sending_user = $user_helper->createRandomUser();
        $address_helper->createNewAddress($sending_user, [
            'type'     => 'BTC',
            'address'  => '1AAAA9999xxxxxxxxxxxxxxxxxxxtA4f45',
            'label'    => 'Issuing Addr Nine',
            'verified' => 1,
            'public'   => 1,
        ]);
        $bvam_client_mock->shouldReceive('getAssetInfo')->withArgs(['COINBBB'])->andReturn([
            'asset' => 'COINBBB',
            'assetInfo' => [
                'issuer' => '1AAAA9999xxxxxxxxxxxxxxxxxxxtA4f45',
            ],
        ]);
        $bvam_client_mock->shouldReceive('getAssetInfo')->withArgs(['COINCCC'])->andReturn([
            'asset' => 'COINCCC',
            'assetInfo' => [
                'issuer' => '1AAAA9999xxxxxxxxxxxxxxxxxxxtA4f45',
            ],
        ]);

        // create users
        $users = [];
        $users[0] = $user_helper->createRandomUser();
        $users[1] = $user_helper->createRandomUser();
        $users[2] = $user_helper->createRandomUser();

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($sending_user);
        $token = $oauth_helper->connectUserSession($sending_user, $oauth_client);
        $api_tester = new OauthUserAPITester($token);

        // create new addresses
        $addresses = [];

        // user 0, Address 0
        $user_offset = 0; $address_offset = 0;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);
        $address_helper->addBalancesToAddress([
            'COINAAA' => 1,
            'COINBBB' => 2,
            'COINCCC' => 3,
        ], $addresses[$address_offset]);

        // user 1, Address 1
        $user_offset = 1; $address_offset = 1;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);
        $address_helper->addBalancesToAddress([
            'COINBBB' => 50,
            'COINCCC' => 50,
            'USERONE' => 1,
        ], $addresses[$address_offset]);

        // user 2, Address 2 (not public)
        $user_offset = 2; $address_offset = 2;
        $addresses[$address_offset] = $address_helper->createNewAddress($users[$user_offset], [
            'type'     => 'BTC',
            'address'  => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 0,
        ]);
        $address_helper->addBalancesToAddress([
            'COINAAA' => 50,
            'COINBBB' => 50,
            'COINCCC' => 50,
            'USERTWO' => 1,
        ], $addresses[$address_offset]);

        // send message to 1 COINBBB (should be user 0,1)
        $message_params = ['token' => 'COINBBB', 'quantity' => 1, 'message' => 'Hi there users 0 and 1!'];
        // expect publish
        $pubnub_mock->shouldReceive('publish')->withArgs([$users[0]->getChannelName(), [
            'token'    => $message_params['token'],
            'quantity' => $message_params['quantity'],
            'msg'      => $message_params['message'],
        ]])->times(1);
        $pubnub_mock->shouldReceive('publish')->withArgs([$users[1]->getChannelName(), [
            'token'    => $message_params['token'],
            'quantity' => $message_params['quantity'],
            'msg'      => $message_params['message'],
        ]])->times(1);
        $response = $api_tester->callJSON('POST', route('api.messenger.broadcast'), $message_params);
        PHPUnit::assertTrue($response['success']);
        PHPUnit::assertEquals(2, $response['count']);

        // send message to 1 COINCCC (should be user 1,2,3)
        $message_params = ['token' => 'COINCCC', 'quantity' => 1, 'message' => 'Hi there users 0, 1 and 2!'];
        // expect publish
        $pubnub_mock->shouldReceive('publish')->withArgs([$users[0]->getChannelName(), [
            'token'    => $message_params['token'],
            'quantity' => $message_params['quantity'],
            'msg'      => $message_params['message'],
        ]])->times(1);
        $pubnub_mock->shouldReceive('publish')->withArgs([$users[1]->getChannelName(), [
            'token'    => $message_params['token'],
            'quantity' => $message_params['quantity'],
            'msg'      => $message_params['message'],
        ]])->times(1);
        $pubnub_mock->shouldReceive('publish')->withArgs([$users[2]->getChannelName(), [
            'token'    => $message_params['token'],
            'quantity' => $message_params['quantity'],
            'msg'      => $message_params['message'],
        ]])->times(1);
        $response = $api_tester->callJSON('POST', route('api.messenger.broadcast'), $message_params);
        PHPUnit::assertTrue($response['success']);
        PHPUnit::assertEquals(3, $response['count']);
    }

    // ------------------------------------------------------------------------
    
    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->mock_builder;
    }

    protected function mockPubnub() {
        $mock = Mockery::mock('Pubnub\Pubnub');
        app()->instance('Pubnub\Pubnub', $mock);
        return $mock;
    }

    protected function mockBVAMClient() {
        $mock = Mockery::mock('Tokenly\BvamApiClient\BVAMClient');
        app()->instance('Tokenly\BvamApiClient\BVAMClient', $mock);
        return $mock;
    }

}
