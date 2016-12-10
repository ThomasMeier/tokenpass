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


    public function authorizeAllUsers() {
        $user_repository = app('Tokenpass\Repositories\UserRepository');
        $all_users = $user_repository->findAll();
        $count = count($all_users);
        foreach ($all_users as $offset => $user) {
            $this->authorizeUser($user);
            if ($offset % 25 == 0 OR $offset >= $count-1) {
                Log::debug("Authorized ".($offset+1)." of {$count} users");
            }
        }
    }

    public function authorizeUser(User $user) {
        $this->authorizeUserControlChannel($user);
    }

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

        if ($token_chat['global']) {
            $new_users = $user_repository->findAll();
        } else {
            // new user ids (users that should have access)
            $new_users = $this->findUsersWithTokens($token_chat['tca_rules']);
        }
        $new_users_by_id = collect($new_users)->keyBy('id');
        $new_user_ids = $new_users_by_id->keys();

        // old user ids (users that already have access)
        $chat_channel = "chat-".$token_chat->getChannelName();
        $old_user_ids = $auth->findUserIDsByChannel($chat_channel)->pluck('user_id');

        // add users
        $user_ids_to_add = $new_user_ids->diff($old_user_ids);
        foreach($user_ids_to_add as $user_id_to_add) {
            $user = $new_users_by_id[$user_id_to_add];
            $this->authorizeUserToChat($user, $token_chat);
        }

        // remove users
        $user_ids_to_delete = $old_user_ids->diff($new_user_ids);
        foreach($user_ids_to_delete as $user_id_to_delete) {
            $user = $user_repository->findById($user_id_to_delete);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }
    }

    public function authorizeUserToChat(User $user, TokenChat $token_chat) {
        $this->authorizeUserControlChannel($user);
        $this->addUserToChat($user, $token_chat);
    }

    public function deauthorizeUserFromChat(User $user, TokenChat $token_chat) {
        $this->removeUserFromChat($user, $token_chat);
    }

    public function removeChat(TokenChat $token_chat) {
        $auth = $this->tca_messenger_auth;
        $user_repository = app('Tokenpass\Repositories\UserRepository');

        // remove and deauthorize all users
        $chat_channel = "chat-".$token_chat->getChannelName();
        $user_ids = $auth->findUserIDsByChannel($chat_channel)->pluck('user_id');
        foreach($user_ids as $user_id) {
            $user = $user_repository->findById($user_id);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }

    }

    // ------------------------------------------------------------------------
    
    protected function addUserToChat(User $user, TokenChat $token_chat) {
        $channel_name            = $token_chat->getChannelName();
        $chat_channel            = "chat-{$channel_name}";
        $chat_presence_channel   = $chat_channel."-pnpres";
        $chat_identities_channel = "identities-{$channel_name}";

        $auth = $this->tca_messenger_auth;

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

        $auth = $this->tca_messenger_auth;

        // send exit message
        $this->tca_messenger_actions->removeUserFromChat($user, $token_chat);

        // remove identity
        $this->tca_messenger_actions->removeIdentity($user, $token_chat);

        // revoke privileges (should be last)
        $auth->revokeUser($user, $chat_identities_channel);
        $auth->revokeUser($user, $chat_channel);
        $auth->revokeUser($user, $chat_presence_channel);
    }

    protected function authorizeUserControlChannel(User $user) {
        $auth = $this->tca_messenger_auth;
        $user_control_channel = "control-".$user->getChannelName();

        // tokenpass can read/write to user's control channel
        $auth->authorizeTokenpass($read=true, $write=true, $user_control_channel);

        // user can read from control channel
        $auth->authorizeUser($user, $read=true, $write=false, $user_control_channel);

    }

}
