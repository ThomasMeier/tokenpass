
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use \PHPUnit_Framework_Assert as PHPUnit;

class TCAMessengerAuthTest extends TestCase
{

    protected $use_database = true;

    public function testAuthorizeChat() {
        // user
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper');
        $user = $user_helper->createNewUser();
        $address = $address_helper->createNewAddress($user);
        $address_helper->addBalancesToAddress(['MYCOIN' => 50], $address);
        $user_channel = $user->getChannelName();

        // chat
        $token_chat_helper = app('TokenChatHelper');
        $token_chat = $token_chat_helper->createNewTokenChat($user);
        $chat_id = $token_chat->getChannelName();

        // mock
        $tca_messenger_auth_mock = Mockery::mock(TCAMessengerAuth::class);
        $tca_messenger_auth_mock->makePartial();
        $tca_messenger_auth_mock->shouldReceive('grant');
        // authorize tokenpass for identities channel
        $tca_messenger_auth_mock->shouldReceive('authorizeTokenpass')->withArgs([$read=true, $write=true, "identities-{$chat_id}"])->once();
        // authorize tokenpass for chat channel
        $tca_messenger_auth_mock->shouldReceive('authorizeTokenpass')->withArgs([$read=true, $write=true, "chat-{$chat_id}"])->once();

        // tokenpass can read/write to control channel
        $tca_messenger_auth_mock->shouldReceive('authorizeTokenpass')->withArgs([$read=true, $write=true, "control-{$user_channel}"])->once();

        // user can read from control channel
        $tca_messenger_auth_mock->shouldReceive('authorizeUser')->withArgs([Mockery::on(function($user_b) use ($user) {
            return $user_b['id'] === $user['id'];
        }), $read=true, $write=false, "control-{$user_channel}"])->once();

        // user can read/write to identities, chat and presence channel
        $tca_messenger_auth_mock->shouldReceive('authorizeUser')->withArgs([Mockery::on(function($user_b) use ($user) {
            return $user_b['id'] === $user['id'];
        }), $read=true, $write=false, "identities-{$chat_id}"])->once();
        $tca_messenger_auth_mock->shouldReceive('authorizeUser')->withArgs([Mockery::on(function($user_b) use ($user) {
            return $user_b['id'] === $user['id'];
        }), $read=true, $write=false, "chat-{$chat_id}"])->once();
        $tca_messenger_auth_mock->shouldReceive('authorizeUser')->withArgs([Mockery::on(function($user_b) use ($user) {
            return $user_b['id'] === $user['id'];
        }), $read=true, $write=false, "chat-{$chat_id}-pnpres"])->once();

        $tca_messenger_auth_mock->tokenpass_auth_key = 'tokenpass_auth_key_TEST';
        app()->instance(TCAMessengerAuth::class, $tca_messenger_auth_mock);


        // messages
        $tca_messenger_actions_mock = Mockery::mock(TCAMessengerActions::class, [Mockery::mock('Pubnub\Pubnub')->shouldIgnoreMissing()]);
        $tca_messenger_actions_mock->makePartial();
        $expected_message = [
            'action'    => 'identityJoined',
            'args'      => [
                'chatId'    => $token_chat->getChannelName(),
                'username'  => $user['username'],
                'role'      => 'member',
                'avatar'    => null,
                'publicKey' => $user->getECCPublicKey(),
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
            ->withArgs(["control-{$user_channel}", $expected_message, 'sendChatInvitation'])
            ->once();
        app()->instance(TCAMessengerActions::class, $tca_messenger_actions_mock);


        // authorize
        $tca_messenger = app('Tokenpass\Providers\TCAMessenger\TCAMessenger');
        $tca_messenger->authorizeChat($token_chat);
    }

    public function testSyncChat() {
        $users = [];
        $addresses = [];

        $address_helper    = app('AddressHelper');
        $user_helper       = app('UserHelper');
        $token_chat_helper = app('TokenChatHelper');

        // create a chat
        $owner = $user_helper->createRandomUser(['username' => 'owner001']);
        $token_chat = $token_chat_helper->createNewTokenChat($owner);
        $chat_id = $token_chat->getChannelName();

        // add 3 users
        for ($user_offset=0; $user_offset < 3; $user_offset++) { 
            $users[$user_offset] = $user_helper->createRandomUser(['username' => 'user_'.sprintf('%02d', $user_offset)]);
            $address[$user_offset] = $address_helper->createNewAddress($users[$user_offset]);
            $address_helper->addBalancesToAddress(['MYCOIN' => 50], $address[$user_offset]);
            $user_channel = $users[$user_offset]->getChannelName();
        }

        // sync the 3 users to the chat
        // mock
        $tca_messenger_auth_mock = Mockery::mock(TCAMessengerAuth::class);
        $tca_messenger_auth_mock->makePartial();
        $tca_messenger_auth_mock->shouldReceive('grant');
        $tca_messenger_auth_mock->shouldReceive('revoke');
        $tca_messenger_auth_mock->tokenpass_auth_key = 'tokenpass_auth_key_TEST';
        app()->instance(TCAMessengerAuth::class, $tca_messenger_auth_mock);

        // mock actions
        app()->instance(TCAMessengerActions::class, Mockery::mock(TCAMessengerActions::class)->shouldIgnoreMissing());

        // authorize
        $tca_messenger = app('Tokenpass\Providers\TCAMessenger\TCAMessenger');
        $tca_messenger->authorizeChat($token_chat);


        // now create a 4th new user
        $user_offset = 3;
        $users[$user_offset] = $user_helper->createRandomUser(['username' => 'user_'.sprintf('%02d', $user_offset)]);
        $address[$user_offset] = $address_helper->createNewAddress($users[$user_offset]);
        $address_helper->addBalancesToAddress(['MYCOIN' => 50], $address[$user_offset]);
        $user_channel = $users[$user_offset]->getChannelName();

        // and make user 2 unauthorized
        $address_helper->updateAddressBalances(['MYCOIN' => 1], $address[1]);

        // re-sync
        $tca_messenger->syncUsersWithChat($token_chat);

        // now check the authorization table
        $records = DB::table('pubnub_user_access')->get();
        $user_ids_authorized = $records
            ->filter(function($r) {
                if (substr($r->channel, 0, 8) == 'control-') { return false; }
                return !!$r->read;
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->toArray();
        PHPUnit::assertCount(3, $user_ids_authorized);
        $expected_user_ids = [
            $users[0]['id'],
            $users[2]['id'],
            $users[3]['id'],
        ];
        PHPUnit::assertEquals($expected_user_ids, $user_ids_authorized);
    }


}