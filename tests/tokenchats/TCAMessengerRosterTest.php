
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Providers\TCAMessenger\TCAMessengerRoster;
use \PHPUnit_Framework_Assert as PHPUnit;

class TCAMessengerRosterTest extends TestCase
{

    protected $use_database = true;

    public function testCreateChatAddsOwnerAsCreator() {
        app('TCAMessengerHelper')->mockAll();

        // user
        $user_helper       = app('UserHelper');
        $token_chat_helper = app('TokenChatHelper');

        $user = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);
        $user_2 = $user_helper->createRandomUser();


        $messenger = app(TCAMessenger::class);
        $messenger->addUserToChat($user, $token_chat);
        $messenger->addUserToChat($user_2, $token_chat);


        $all_db_rows = DB::table('chat_rosters')->get();
        PHPUnit::assertEquals('creator', $all_db_rows[0]->role);
        PHPUnit::assertEquals('member', $all_db_rows[1]->role);
    }

    public function testAddUserToRoster() {
        // user
        $user_helper       = app('UserHelper');
        $token_chat_helper = app('TokenChatHelper');

        $user = $user_helper->createRandomUser();
        $token_chat = $token_chat_helper->createNewTokenChat($user);

        $roster = app(TCAMessengerRoster::class);
        $roster->addUserToChat($user, $token_chat, 'member');

        $this->verifyRoster([[$user, $token_chat]]);
    }

    public function testGetRoster() {
        // user
        $user_helper       = app('UserHelper');
        $token_chat_helper = app('TokenChatHelper');

        $user = $user_helper->createRandomUser();

        // add chats and users
        $token_chats = [];
        $users = [];
        $token_chats[0] = $token_chat_helper->createNewTokenChat($user);
        $token_chats[1] = $token_chat_helper->createNewTokenChat($user);
        $users[0] = $user_helper->createRandomUser();
        $users[1] = $user_helper->createRandomUser();

        $roster = app(TCAMessengerRoster::class);
        $roster->addUserToChat($users[0], $token_chats[0], 'member');
        $roster->addUserToChat($users[1], $token_chats[0], 'creator');

        $roster_rows = $roster->loadChatRoster($token_chats[0]);
        PHPUnit::assertCount(2, $roster_rows);
        PHPUnit::assertEquals($users[0]['id'], $roster_rows[0]->user_id);
        PHPUnit::assertEquals($users[1]['id'], $roster_rows[1]->user_id);
        PHPUnit::assertEquals('member', $roster_rows[0]->role);
        PHPUnit::assertEquals('creator', $roster_rows[1]->role);

        PHPUnit::assertTrue($roster->userIsAddedToChat($users[0], $token_chats[0]));
        PHPUnit::assertTrue($roster->userIsAddedToChat($users[1], $token_chats[0]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($users[0], $token_chats[1]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($users[1], $token_chats[1]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($user, $token_chats[0]));
    }

    public function testDeleteFromRoster() {
        // user
        $user_helper       = app('UserHelper');
        $token_chat_helper = app('TokenChatHelper');

        $user = $user_helper->createRandomUser();

        // add chats and users
        $token_chats = [];
        $users = [];
        $token_chats[0] = $token_chat_helper->createNewTokenChat($user);
        $token_chats[1] = $token_chat_helper->createNewTokenChat($user);
        $users[0] = $user_helper->createRandomUser();
        $users[1] = $user_helper->createRandomUser();
        $users[2] = $user_helper->createRandomUser();

        $roster = app(TCAMessengerRoster::class);
        $roster->addUserToChat($users[0], $token_chats[0], 'member');
        $roster->addUserToChat($users[1], $token_chats[0], 'member');
        $roster->addUserToChat($users[2], $token_chats[0], 'member');

        // remove 1 real user
        $roster->removeUserFromChat($users[1], $token_chats[1]); // doesn't exist
        $roster->removeUserFromChat($users[1], $token_chats[0]);

        PHPUnit::assertTrue($roster->userIsAddedToChat($users[0], $token_chats[0]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($users[1], $token_chats[0]));
        PHPUnit::assertTrue($roster->userIsAddedToChat($users[2], $token_chats[0]));

        // delete all
        $roster->removeAllUsersFromChat($token_chats[0]);

        PHPUnit::assertFalse($roster->userIsAddedToChat($users[0], $token_chats[0]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($users[1], $token_chats[0]));
        PHPUnit::assertFalse($roster->userIsAddedToChat($users[2], $token_chats[0]));
    }

    // ------------------------------------------------------------------------
    
    protected function verifyRoster($expected_entry_objects) {
        $actual_entries = [];
        $all_db_rows = DB::table('chat_rosters')->get();
        foreach ($all_db_rows as $db_row) {
            $actual_entries[] = [$db_row->user_id, $db_row->chat_id];
        }

        $expected_entries = [];
        foreach($expected_entry_objects as $expected_entry_object_pair) {
            $expected_entries[] = [$expected_entry_object_pair[0]['id'], $expected_entry_object_pair[1]['id']];
        }

        PHPUnit::assertEquals($expected_entries, $actual_entries, "Entries mismatch.  DB Rows were ".json_encode($all_db_rows, 192));
    }

}