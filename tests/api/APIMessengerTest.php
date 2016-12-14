<?php

use Illuminate\Support\Facades\DB;
use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;

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

    // ------------------------------------------------------------------------
    // roster

    public function testNotAuthorizedMessengerJoinChatAPI() {
        // mock
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // not authorized
        $route_spec = ['api.messenger.joinroster', $token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('POST', $route_spec, [], 403);
        PHPUnit::assertContains('Not authorized for this chat', $result['message']);
    }

    public function testNotFoundMessengerJoinChatAPI() {
        // mock
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // not found
        $route_spec = ['api.messenger.joinroster', 'foobar'];
        $result = $api_tester->expectAuthenticatedResponse('POST', $route_spec, [], 404);
        PHPUnit::assertContains('Chat not found', $result['message']);
    }

    public function testMessengerJoinChatAPI() {
        // mock TCAMessengerAuth
        $tca_messenger_auth_mock = Mockery::mock(TCAMessengerAuth::class);
        $tca_messenger_auth_mock->makePartial();
        $tca_messenger_auth_mock->shouldReceive('grant');
        $tca_messenger_auth_mock->shouldReceive('revoke');
        $tca_messenger_auth_mock->tokenpass_auth_key = 'tokenpass_auth_key_TEST';
        app()->instance(TCAMessengerAuth::class, $tca_messenger_auth_mock);

        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');


        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // expect identity messages
        $chat_id = $token_chat->getChannelName();
        $user1_channel = $user1->getChannelName();
        $tca_messenger_actions_mock = Mockery::mock(TCAMessengerActions::class, [Mockery::mock('Pubnub\Pubnub')->shouldIgnoreMissing()]);
        $tca_messenger_actions_mock->makePartial();
        $expected_message = [
            'action'    => 'identityJoined',
            'args'      => [
                'chatId'    => $token_chat->getChannelName(),
                'username'  => $user1['username'],
                'role'      => 'member',
                'avatar'    => null,
                'publicKey' => $user1->getECCPublicKey(),
            ]
        ];
        $tca_messenger_actions_mock->shouldReceive('_publish')
            ->withArgs(["identities-{$chat_id}", $expected_message, 'sendIdentity'])
            ->once();

        $expected_message = [
            'action' => 'addedToChat',
            'args'   => [
                'chatName' => $token_chat['name'],
                'id'       => $token_chat->getChannelName(),
            ]
        ];
        $tca_messenger_actions_mock->shouldReceive('_publish')
            ->withArgs(["control-{$user1_channel}", $expected_message, 'sendChatInvitation'])
            ->once();

        app()->instance(TCAMessengerActions::class, $tca_messenger_actions_mock);


        // authorize the user
        app(TCAMessenger::class)->authorizeUserToChat($user1, $token_chat);

        $route_spec = ['api.messenger.joinroster', $token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('POST', $route_spec);
        PHPUnit::assertEquals([
                'success' => true,
            ], $result
        );

        // check user is joined
        $all_db_rows = DB::table('chat_rosters')->get();
        PHPUnit::assertCount(1, $all_db_rows);
        PHPUnit::assertEquals($user1['id'], $all_db_rows[0]->user_id);
        PHPUnit::assertEquals($token_chat['id'], $all_db_rows[0]->chat_id);

    }


}