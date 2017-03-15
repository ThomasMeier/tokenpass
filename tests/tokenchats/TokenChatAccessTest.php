
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenpass\Models\Address;
use Tokenpass\Models\TokenChat;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Repositories\TokenChatRepository;
use \PHPUnit_Framework_Assert as PHPUnit;

class TokenChatAccessTest extends TestCase
{

    protected $use_database = true;

    public function testTokenChatAccess_create() {
        $token_chat_helper = app('TokenChatHelper');
        $token_chat = $token_chat_helper->createNewTokenChat();
        $token_chat_repository = app(TokenChatRepository::class);

        $index_entries = DB::table('token_chat_access')->get();
        PHPUnit::assertNotEmpty($index_entries);
        PHPUnit::assertCount(1, $index_entries);

        PHPUnit::assertEquals(1000000000, $index_entries[0]->amount);
        PHPUnit::assertEquals('MYCOIN', $index_entries[0]->asset);
        PHPUnit::assertEquals($token_chat['id'], $index_entries[0]->token_chat_id);

        // find
        $found_token_chats = $token_chat_repository->findTokenChatsByAsset('MYCOIN', true);
        PHPUnit::assertCount(1, $found_token_chats);
        PHPUnit::assertEquals('My New Chat', $found_token_chats[0]['name']);
        PHPUnit::assertEquals(1, $token_chat_repository->getTokenChatsCountByAsset('MYCOIN', true));
    }

    public function testTokenChatAccess_update() {
        $token_chat_helper = app('TokenChatHelper');
        $token_chat_repository = app(TokenChatRepository::class);
        $tca_messenger = app(TCAMessenger::class);

        // create
        $token_chat = $token_chat_helper->createNewTokenChat();
        $index_entries = DB::table('token_chat_access')->get();
        PHPUnit::assertCount(1, $index_entries);

        // update
        $vars = [
            'name'     => 'My New Chat EDITED',
            'tca_rules' => $tca_messenger->makeSimpleTCAStack(11, 'OTHERCOIN'),
        ];
        $token_chat_repository->update($token_chat, $vars);

        $index_entries = DB::table('token_chat_access')->get();
        PHPUnit::assertCount(1, $index_entries);
        PHPUnit::assertEquals(1100000000, $index_entries[0]->amount);
        PHPUnit::assertEquals('OTHERCOIN', $index_entries[0]->asset);

        // inactivate
        $vars = [
            'active' => false,
        ];
        $token_chat_repository->update($token_chat, $vars);

        // find
        $found_token_chats = $token_chat_repository->findTokenChatsByAsset('OTHERCOIN', true);
        PHPUnit::assertCount(0, $found_token_chats);
        $found_token_chats = $token_chat_repository->findTokenChatsByAsset('OTHERCOIN', false);
        PHPUnit::assertCount(1, $found_token_chats);
        $found_token_chats = $token_chat_repository->findTokenChatsByAsset('OTHERCOIN', null);
        PHPUnit::assertCount(1, $found_token_chats);
    }

    public function testTokenChatAccess_delete() {
        $token_chat_helper = app('TokenChatHelper');
        $token_chat_repository = app(TokenChatRepository::class);
        $tca_messenger = app(TCAMessenger::class);

        // create
        $token_chat = $token_chat_helper->createNewTokenChat();
        $index_entries = DB::table('token_chat_access')->get();
        PHPUnit::assertCount(1, $index_entries);

        // delete
        $token_chat_repository->delete($token_chat);

        $index_entries = DB::table('token_chat_access')->get();
        PHPUnit::assertCount(0, $index_entries);

        $found_token_chats = $token_chat_repository->findTokenChatsByAsset('MYCOIN', null);
        PHPUnit::assertCount(0, $found_token_chats);
    }

    // ------------------------------------------------------------------------
    
    public function setUp()
    {
        parent::setUp();

        // mock TCAMessengerAuth
        $tca_messenger_auth_mock = Mockery::mock(TCAMessengerAuth::class);
        $tca_messenger_auth_mock->makePartial();
        $tca_messenger_auth_mock->shouldReceive('grant');
        $tca_messenger_auth_mock->shouldReceive('revoke');
        $tca_messenger_auth_mock->tokenpass_auth_key = 'tokenpass_auth_key_TEST';
        app()->instance(TCAMessengerAuth::class, $tca_messenger_auth_mock);
    }


}
