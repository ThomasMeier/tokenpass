<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use Pubnub\Pubnub;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\TokenChat;
use Tokenpass\Models\User;

class TCAMessengerActions
{

    function __construct(Pubnub $pubnub) {
        $this->pubnub = $pubnub;
        $this->pubnub->setAuthKey(env('PUBNUB_TOKENPASS_AUTH_KEY'));
    }

    // ------------------------------------------------------------------------
    // Identities

    public function sendIdentity(User $user, TokenChat $token_chat, $role) {
        $chat_identities_channel = "identities-".$token_chat->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action'    => 'identityJoined',
            'args'      => [
                'chatId'    => $token_chat->getChannelName(),
                'username'  => $user['username'],
                'role'      => $role,
                'avatar'    => null,
                'publicKey' => $user->getECCPublicKey(),
            ]
        ], __FUNCTION__);
    }

    public function removeIdentity(User $user, TokenChat $token_chat) {
        $chat_identities_channel = "identities-".$token_chat->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action'    => 'identityLeft',
            'args'      => [
                'chatId'    => $token_chat->getChannelName(),
                'username'  => $user['username'],
            ]
        ], __FUNCTION__);
    }

    // ------------------------------------------------------------------------
    // Control

    public function sendChatInvitation(User $user, TokenChat $token_chat) {
        $chat_identities_channel = "control-".$user->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action' => 'addedToChat',
            'args'   => [
                'chatName' => $token_chat['name'],
                'id'       => $token_chat->getChannelName(),
            ]
        ], __FUNCTION__);
    }

    public function removeUserFromChat(User $user, TokenChat $token_chat) {
        $chat_identities_channel = "control-".$user->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action' => 'removedFromChat',
            'args'   => [
                'id'       => $token_chat->getChannelName(),
            ]
        ], __FUNCTION__);
    }

    // ------------------------------------------------------------------------
    // Events

    public function tcaUpdated(User $user, $args=[]) {
        $chat_identities_channel = "event-".$user->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action' => 'tcaUpdated',
            'args'   => $args,
        ], __FUNCTION__);
    }

    public function creditsUpdated(User $user, $args) {
        $chat_identities_channel = "event-".$user->getChannelName();
        return $this->_publish($chat_identities_channel, [
            'action' => 'creditsUpdated',
            'args'   => $args,
        ], __FUNCTION__);
    }



    // ------------------------------------------------------------------------

    // only public for mocking - don't call directly
    public function _publish($channel, $message, $desc=null) {
        EventLog::debug('pubnub.publish', ['channel'=> $channel, 'messageType'=> $desc]);
        return $this->processResponse($this->pubnub->publish($channel, $message));
    }

    // ------------------------------------------------------------------------
    
    protected function processResponse($response) {
        if ($this->isErrorResponse($response)) {
            EventLog::logError('pubnub.publishError', $response['message']);
            throw new Exception($response['message'], 1);
        }
        return $response;
    }

    protected function isErrorResponse($response) {
        return (isset($response['error']) AND $response['error']);
    }


}
