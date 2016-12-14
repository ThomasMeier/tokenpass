<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use Illuminate\Support\Facades\DB;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\TokenChat;
use Tokenpass\Models\User;

class TCAMessengerRoster
{
    function __construct() {
    }

    // public function addUserToChatOnceAndProcess(User $user, TokenChat $token_chat, $callback_fn) {
    //     return DB::transaction(function($user, $token_chat, $callback_fn) {
    //         if ($this->userIsAddedToChat($user, $token_chat)) {
    //             return;
    //         }

    //         $this->addUserToChat($user, $token_chat);

    //         return $callback_fn()
    //     });
    // }

    public function addUserToChat(User $user, TokenChat $token_chat) {
        return DB::table('chat_rosters')->insert([
            'user_id' => $user['id'],
            'chat_id' => $token_chat['id'],
            'updated_at' => time(),
        ]);
    }

    public function removeAllUsersFromChat(TokenChat $token_chat) {
        return DB::table('chat_rosters')
            ->where('chat_id', '=', $token_chat['id'])
            ->delete();
    }


    public function removeUserFromChat(User $user, TokenChat $token_chat) {
        return DB::table('chat_rosters')
            ->where('chat_id', '=', $token_chat['id'])
            ->where('user_id', '=', $user['id'])
            ->delete();
    }


    public function loadChatRoster(TokenChat $token_chat) {
        return DB::table('chat_rosters')->where('chat_id', '=', $token_chat['id'])->get();
    }

    public function userIsAddedToChat(User $user, TokenChat $token_chat) {
        $rows = DB::table('chat_rosters')
            ->select('1')
            ->where('chat_id', '=', $token_chat['id'])
            ->where('user_id', '=', $user['id'])
            ->get();
        return $rows->count() > 0 ? true : false;
    }



}
