<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use Illuminate\Support\Facades\Log;
use Pubnub\Pubnub;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\TCA\Access;
use Tokenpass\Models\Address;
use Tokenpass\Models\TokenChat;
use Tokenpass\Models\User;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;

class TCAMessenger
{

    function __construct(BVAMClient $bvam_client, TCAMessengerAuth $tca_messenger_auth, TCAMessengerActions $tca_messenger_actions, Pubnub $pubnub) {
        $this->bvam_client           = $bvam_client;
        $this->tca_messenger_auth    = $tca_messenger_auth;
        $this->tca_messenger_actions = $tca_messenger_actions;
        $this->pubnub                = $pubnub;
    }

    public function userCanSendMessages(User $user, $token) {
        // get issuer address
        $bvam = $this->bvam_client->getAssetInfo($token);
        $issuer_address = $bvam['assetInfo']['issuer'];

        // get all authenticated addresses for this user
        $addresses = Address::getAddressList($user['id'], $public=null, $active=null, $verified=true);

        foreach($addresses as $address) {
            if ($address['address'] == $issuer_address) {
                return true;
            }
        }
        return false;
    }

    public function findUsersWithTokens($tca_stack) {
        $address_repository = app('Tokenpass\Repositories\AddressRepository');
        $user_repository = app('Tokenpass\Repositories\UserRepository');
        $tokens = collect($tca_stack)->pluck('asset')->unique()->toArray();

        // rough 1st pass filter
        $possible_user_ids = $address_repository->findUserIDsWithToken($tokens, $public_only=false, $active_only=true, $verified_only=true);

        // detailed tca check
        $users = [];

        $tca = new Access();
        foreach($possible_user_ids as $possible_user_id) {
            $balances = Address::getAllUserBalances($possible_user_id, $filter_disabled = true, $and_provisional = true, $subtract_loans = true);
            $has_access = $tca->checkAccess($tca_stack, $balances);

            if ($has_access) {
                // load the user
                $user = $user_repository->findById($possible_user_id);

                // add to the results
                $users[] = $user;
            }
        }

        return $users;

    }

    public function makeSimpleTCAStack($quantity, $token) {
        return [
            [
                'asset'   => $token,
                'amount'  => CurrencyUtil::valueToSatoshis($quantity),
                'op'      => '>=',
                'stackOp' => 'AND',
            ]
        ];
    }

    // public function broadcast($quantity, $token, $message_contents) {
    //     $tca_stack = $this->makeSimpleTCAStack($quantity, $token);

    //     $count = 0;
    //     $recipient_users = $this->findUsersWithTokens($tca_stack);
    //     foreach($recipient_users as $user) {
    //         $message = [
    //             'quantity' => $quantity,
    //             'token'    => $token,
    //             'msg'      => $message_contents,
    //         ];

    //         try {
    //             $this->pubnub->publish($user->getChannelName(), $message);
    //             ++$count;
    //         } catch (Exception $e) {
    //             EventLog::logError('pubnub.publishFailed', $e, ['user' => $user['id']]);
    //         }

    //     }

    //     EventLog::info('pubnub.publish', ['quantity' => $quantity, 'token' => $token, 'count' => $count,]);
    //     return $count;
    // }

    public function authorizeChat(TokenChat $token_chat) {
        $auth = $this->tca_messenger_auth;

        $channel_name            = $token_chat->getChannelName();
        $chat_channel            = "chat-{$channel_name}";
        $chat_presence_channel   = $chat_channel."-pnpres";
        $chat_identities_channel = "identities-{$channel_name}";

        // authorize tokenpass for identities channel
        $auth->authorizeTokenpass($read=true, $write=true, $chat_identities_channel);

        // authorize tokenpass for chat channel
        $auth->authorizeTokenpass($read=true, $write=true, $chat_channel);

        // authorize all users
        $this->syncUsersWithChat($token_chat);
    }

    public function syncUsersWithChat(TokenChat $token_chat) {
        $auth = $this->tca_messenger_auth;
        $user_repository = app('Tokenpass\Repositories\UserRepository');

        // new user ids (users that should have access)
        $new_users = $this->findUsersWithTokens($token_chat['tca_rules']);
        $new_users_by_id = collect($new_users)->keyBy('id');
        $new_user_ids = $new_users_by_id->keys();

        // old user ids (users that already have access)
        $chat_channel = "chat-".$token_chat->getChannelName();
        $old_user_ids = $auth->findUserIDsByChannel($chat_channel)->pluck('user_id');

        // add users
        $user_ids_to_add = $new_user_ids->diff($old_user_ids);
        foreach($user_ids_to_add as $user_id_to_add) {
            $user = $new_users_by_id[$user_id_to_add];
            $this->addUserToChat($user, $token_chat);
        }


        // remove users
        $user_ids_to_delete = $old_user_ids->diff($new_user_ids);
        foreach($user_ids_to_delete as $user_id_to_delete) {
            $user = $user_repository->findById($user_id_to_delete);
            $this->removeUserFromChat($user, $token_chat);
        }


    }


    protected function addUserToChat(User $user, TokenChat $token_chat) {
        $channel_name            = $token_chat->getChannelName();
        $chat_channel            = "chat-{$channel_name}";
        $chat_presence_channel   = $chat_channel."-pnpres";
        $chat_identities_channel = "identities-{$channel_name}";
        $user_control_channel    = "control-".$user->getChannelName();

        $auth = $this->tca_messenger_auth;

        // tokenpass can read/write to user's control channel
        $auth->authorizeTokenpass($read=true, $write=true, $user_control_channel);

        // user can read from control channel
        $auth->authorizeUser($user, $read=true, $write=false, $user_control_channel);

        // user can read from identities channel
        $auth->authorizeUser($user, $read=true, $write=false, $chat_identities_channel);

        // user can read/write to chat and presence channel
        $auth->authorizeUser($user, $read=true, $write=true, $chat_channel);
        $auth->authorizeUser($user, $read=true, $write=true, $chat_presence_channel);

        // send identity
        $this->tca_messenger_actions->sendIdentity($user, $token_chat);

        // send invitation
        $this->tca_messenger_actions->sendChatInvitation($user, $token_chat);
    }

    protected function removeUserFromChat(User $user, TokenChat $token_chat) {
        $channel_name            = $token_chat->getChannelName();
        $chat_channel            = "chat-{$channel_name}";
        $chat_presence_channel   = $chat_channel."-pnpres";
        $chat_identities_channel = "identities-{$channel_name}";
        $user_control_channel    = "control-".$user->getChannelName();

        $auth = $this->tca_messenger_auth;

        // revoke privileges
        $auth->revokeUser($user, $chat_identities_channel);
        $auth->revokeUser($user, $chat_channel);
        $auth->revokeUser($user, $chat_presence_channel);

        // remove identity

        // send exit message
    }

}
