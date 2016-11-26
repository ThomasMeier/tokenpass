<?php

use Illuminate\Support\Facades\Log;
use Tokenpass\Models\User;

/*
* TokenChatHelper
*/
class TokenChatHelper
{

    public function __construct() {
    }



    public function createNewTokenChat(User $user=null, $token_chat_override_vars=[]) {
        if ($user === null) {
            $user = app('UserHelper')->createNewUser();
        }

        $token_chat_vars = array_merge($this->defaultTokenChatVars($user), $token_chat_override_vars);

        $token_chat_vars = $this->processTCARules($token_chat_vars);

        $token_chat = app('Tokenpass\Repositories\TokenChatRepository')->create($token_chat_vars);

        return $token_chat;
    }

    public function defaultTokenChatVars(User $user) {
        return [
            'user_id'  => $user['id'],
            'name'     => 'My New Chat',
            'token'    => 'MYCOIN',
            'quantity' => 10,
            'active'   => true,
        ];
    }

    protected function processTCARules($token_chat_vars) {
        $tca_messenger = app('Tokenpass\Providers\TCAMessenger\TCAMessenger');
        $tca_rules = $tca_messenger->makeSimpleTCAStack($token_chat_vars['quantity'], $token_chat_vars['token']);

        $token_chat_vars['tca_rules'] = $tca_rules;
        unset($token_chat_vars['token']);
        unset($token_chat_vars['quantity']);
        return $token_chat_vars;
    }

}