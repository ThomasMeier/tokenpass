
<?php

use Illuminate\Support\Facades\Session;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenpass\Models\Address;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use \PHPUnit_Framework_Assert as PHPUnit;

class TokenChatsTest extends TestCase
{

    protected $use_database = true;

    public function testTokenChatId() {
        $token_chat_helper = app('TokenChatHelper');
        $token_chat = $token_chat_helper->createNewTokenChat();

        $base58 = new Base58(null, new GMPService());
        PHPUnit::assertNotEmpty($token_chat->getChannelName());
        PHPUnit::assertEquals(str_replace('-', '', $token_chat['uuid']), bin2hex($base58->decode($token_chat->getChannelName())));
    }

    public function testTokenChatsPageLoad() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user_helper->loginUser($this->app, $user);

        // check loading authorize form
        $response = $this->call('GET', route('tokenchats.index'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('>Token Chats</', $response->getContent());
    }

    public function testNewTokenChat() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user_helper->loginUser($this->app, $user);

        // add a new chat (with errors)
        $vars = [
            'name' => 'My New Chat',
        ];
        $response = $this->call('POST', route('tokenchats.create'), $vars);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('token field is required', Session::get('message'));

        // add a new chat
        $vars = [
            'name'     => 'My New Chat',
            'token'    => 'MYCOIN',
            'quantity' => 10,
        ];
        $response = $this->call('POST', route('tokenchats.create'), $vars);
        $this->assertEquals(302, $response->getStatusCode(), "Failed with error ".Session::get('message'));
        $this->assertContains('Token Chat created', Session::get('message'));

        // check that it was added to the database
        $token_chat_repository = app('Tokenpass\Repositories\TokenChatRepository');
        $chats = $token_chat_repository->findAll();
        PHPUnit::assertCount(1, $chats);
        PHPUnit::assertEquals('My New Chat', $chats[0]['name']);
        PHPUnit::assertEquals([[
            'asset'   => 'MYCOIN',
            'amount'  => 1000000000,
            'op'      => '>=',
            'stackOp' => 'AND',
        ]], $chats[0]['tca_rules']);

    }

    public function testEditTokenChat() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user_helper->loginUser($this->app, $user);


        // add a new chat
        $token_chat_repository = app('Tokenpass\Repositories\TokenChatRepository');
        $vars = [
            'name'     => 'My New Chat',
            'token'    => 'MYCOIN',
            'quantity' => 10,
        ];
        $response = $this->call('POST', route('tokenchats.create'), $vars);
        $this->assertContains('Token Chat created', Session::get('message'));

        $vars = [
            'name'     => 'My New Chat EDITED',
            'token'    => 'OTHERCOIN',
            'quantity' => 11,
            'active'   => false,
        ];
        $chat = $token_chat_repository->findAll()->first();
        $response = $this->call('POST', route('tokenchats.edit', ['id' => $chat['uuid']]), $vars);
        $this->assertContains('Token Chat updated', Session::get('message'));

        $chats = $token_chat_repository->findAll();
        PHPUnit::assertCount(1, $chats);
        PHPUnit::assertEquals('My New Chat EDITED', $chats[0]['name']);
        PHPUnit::assertFalse($chats[0]['active']);
        PHPUnit::assertEquals([[
            'asset'   => 'OTHERCOIN',
            'amount'  => 1100000000,
            'op'      => '>=',
            'stackOp' => 'AND',
        ]], $chats[0]['tca_rules']);

    }

    public function testDeleteTokenChat() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user_helper->loginUser($this->app, $user);


        // add a new chat
        $token_chat_repository = app('Tokenpass\Repositories\TokenChatRepository');
        $vars = [
            'name'     => 'My New Chat',
            'token'    => 'MYCOIN',
            'quantity' => 10,
        ];
        $response = $this->call('POST', route('tokenchats.create'), $vars);
        $this->assertContains('Token Chat created', Session::get('message'));

        // add a new chat
        $vars = [
            'name'     => 'My New Chat 2',
            'token'    => 'MYCOINTWO',
            'quantity' => 10,
        ];
        $response = $this->call('POST', route('tokenchats.create'), $vars);
        $this->assertContains('Token Chat created', Session::get('message'));

        // now delete 
        $chat = $token_chat_repository->findAll()->first();
        $response = $this->call('DELETE', route('tokenchats.delete', ['id' => $chat['uuid']]), $vars);
        $this->assertContains('Token Chat deleted', Session::get('message'));


        $chats = $token_chat_repository->findAll();
        PHPUnit::assertCount(1, $chats);
        PHPUnit::assertEquals('My New Chat 2', $chats[0]['name']);

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
