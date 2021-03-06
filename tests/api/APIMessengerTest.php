<?php

use Illuminate\Support\Facades\DB;
use PHPUnit_Framework_Assert as PHPUnit;
use Tokenly\CurrencyLib\CurrencyUtil;
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

    // ------------------------------------------------------------------------
    // chat authorization

    public function testMessengerAuthorizationAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);
        $global_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => true]);
        $other_global_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => true, 'token' => 'OTHERTOKEN']);
        $unauthorized_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => false, 'token' => 'OTHERTOKEN']);

        // add MYCOIN to user
        $address_helper->addBalancesToAddress(['MYCOIN' => 50], $address_helper->createNewAddress($user1));

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        $route_spec = ['api.messenger.chat.authorization', $token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => true,
                'isGlobal'           => false,
                'tokenAuthorization' => [
                    [
                        'asset'  => 'MYCOIN',
                        'amount' => CurrencyUtil::valueToSatoshis(10),
                    ]
                ],
            ], $result
        );

        $route_spec = ['api.messenger.chat.authorization', $global_token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => true,
                'isGlobal'           => true,
                'tokenAuthorization' => [
                    [
                        'asset'  => 'MYCOIN',
                        'amount' => CurrencyUtil::valueToSatoshis(10),
                    ]
                ],
            ], $result
        );

        $route_spec = ['api.messenger.chat.authorization', $other_global_token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => true,
                'isGlobal'           => true,
                'tokenAuthorization' => [],
            ], $result
        );

        $route_spec = ['api.messenger.chat.authorization', $unauthorized_token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => false,
                'isGlobal'           => false,
                'tokenAuthorization' => [],
            ], $result
        );
    }

    public function testMessengerAuthorizationWithMultipleTokensAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user, ['tca_rules' => [
            [
                'token'    => 'COINONE',
                'quantity' => 1,
            ],
            [
                'token'    => 'COINTWO',
                'quantity' => 1,
            ],
        ]]);

        // add COINONE to user1
        $address_helper->addBalancesToAddress(['COINONE' => 1], $address_helper->createNewAddress($user1));

        // add COINTWO to user2
        $address_helper->addBalancesToAddress(['COINTWO' => 1], $address_helper->createNewAddress($user2));

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user1_token = $oauth_helper->connectUserSession($user1, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // check authorization response
        $route_spec = ['api.messenger.chat.authorization', $token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => true,
                'isGlobal'           => false,
                'tokenAuthorization' => [
                    [
                        'asset'  => 'COINONE',
                        'amount' => CurrencyUtil::valueToSatoshis(1),
                    ],
                ],
            ], $result
        );

        $user2_token = $oauth_helper->connectUserSession($user2, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user2_token);

        $route_spec = ['api.messenger.chat.authorization', $token_chat->getChannelName()];
        $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        PHPUnit::assertEquals([
                'authorized'         => true,
                'isGlobal'           => false,
                'tokenAuthorization' => [
                    [
                        'asset'  => 'COINTWO',
                        'amount' => CurrencyUtil::valueToSatoshis(1),
                    ],
                ],
            ], $result
        );
    }


    // ------------------------------------------------------------------------
    // load chats

    public function testGetChatsAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);
        $other_token_chat = $token_chat_helper->createNewTokenChat($user2);
        $global_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => true]);
        $other_global_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => true, 'token' => 'OTHERTOKEN']);
        $unauthorized_token_chat = $token_chat_helper->createNewTokenChat($user, ['global' => false, 'token' => 'OTHERTOKEN']);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user1);
        $user_token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user_token);

        $result = $api_tester->expectAuthenticatedResponse('GET', 'api.messenger.getchats');
        PHPUnit::assertCount(4, $result);
        PHPUnit::assertEquals($token_chat['uuid'], $result[0]['id']);

        // $route_spec = ['api.messenger.chat.authorization', $token_chat->getChannelName()];
        // PHPUnit::assertEquals([
        //         'authorized'         => true,
        //         'isGlobal'           => false,
        //         'tokenAuthorization' => [
        //             [
        //                 'asset'  => 'MYCOIN',
        //                 'amount' => CurrencyUtil::valueToSatoshis(10),
        //             ]
        //         ],
        //     ], $result
        // );

        // $route_spec = ['api.messenger.chat.authorization', $global_token_chat->getChannelName()];
        // $result = $api_tester->expectAuthenticatedResponse('GET', $route_spec);
        // PHPUnit::assertEquals([
        //         'authorized'         => true,
        //         'isGlobal'           => true,
        //         'tokenAuthorization' => [
        //             [
        //                 'asset'  => 'MYCOIN',
        //                 'amount' => CurrencyUtil::valueToSatoshis(10),
        //             ]
        //         ],
        //     ], $result
        // );

 
    }
    public function testGetChatsWithMultipleTokensAPI() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        // add test users and addresses
        $user = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user, ['tca_rules' => [
            [
                'token'    => 'COINONE',
                'quantity' => 1,
            ],
            [
                'token'    => 'COINTWO',
                'quantity' => 2,
            ],
        ]]);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        $user_token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user_token);

        $result = $api_tester->expectAuthenticatedResponse('GET', 'api.messenger.getchats');
        PHPUnit::assertCount(1, $result);
        PHPUnit::assertEquals($token_chat['uuid'], $result[0]['id']);
        PHPUnit::assertEquals([
            'COINONE' => 1,
            'COINTWO' => 2,
        ], $result[0]['tokens']);

    }

    // ------------------------------------------------------------------------
    
    public function testCreateChatAPI() {
        $user_helper       = app('UserHelper')->setTestCase($this);
        $address_helper    = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');
        app('TCAMessengerHelper')->mockAll();

        $user = $user_helper->createRandomUser();

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        $user_token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user_token);

        // create a chat
        $result = $api_tester->expectAuthenticatedResponse('POST', 'api.messenger.chat.create', $token_chat_helper->getSampleCreateChatPostVars());
        PHPUnit::assertNotEmpty($result);

        PHPUnit::assertEquals([
            'COINONE' => 1,
        ], $result['tokens']);
        PHPUnit::assertEquals('API Chat One', $result['name']);
    }

    public function testEditChatAPI() {
        $user_helper       = app('UserHelper')->setTestCase($this);
        $address_helper    = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');
        app('TCAMessengerHelper')->mockAll();

        $user = $user_helper->createRandomUser();

        // create a chat
        $token_chat = $token_chat_helper->createNewTokenChat($user, ['tca_rules' => [
            [
                'token'    => 'COINONE',
                'quantity' => 1,
            ],
        ]]);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        $user_token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user_token);

        // edit a chat
        $edit_vars = ['tca_rules' => [
            [
                'token'    => 'COINONE',
                'quantity' => 1,
            ],
            [
                'token'    => 'COINTWO',
                'quantity' => 2,
            ],
        ]];
        $result = $api_tester->expectAuthenticatedResponse('POST', ['api.messenger.chat.edit', ['chatId' => $token_chat['uuid']]], $edit_vars);
        PHPUnit::assertNotEmpty($result);

        PHPUnit::assertEquals([
            'COINONE' => 1,
            'COINTWO' => 2,
        ], $result['tokens']);
        PHPUnit::assertEquals('My New Chat', $result['name']);
    }


    public function testGetGetChatAPI() {
        $user_helper       = app('UserHelper')->setTestCase($this);
        $address_helper    = app('AddressHelper');
        $token_chat_helper = app('TokenChatHelper');

        $user = $user_helper->createRandomUser();

        // create a chat
        $token_chat = $token_chat_helper->createNewTokenChat($user, ['tca_rules' => [
            [
                'token'    => 'COINONE',
                'quantity' => 1,
            ],
        ]]);

        // setup api client
        $oauth_helper = app('OAuthClientHelper');
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        $user_token = $oauth_helper->connectUserSession($user, $oauth_client);
        $api_tester = app('OauthUserAPITester')->setToken($user_token);

        // get the chat
        $result = $api_tester->expectAuthenticatedResponse('GET', ['api.messenger.chat.get', ['chatId' => $token_chat['uuid']]]);
        PHPUnit::assertNotEmpty($result);

        PHPUnit::assertEquals([
            'COINONE' => 1,
        ], $result['tokens']);
        PHPUnit::assertEquals('My New Chat', $result['name']);
    }



}