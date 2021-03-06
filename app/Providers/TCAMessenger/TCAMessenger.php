<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pubnub\Pubnub;
use Tokenly\AssetNameUtils\Validator as AssetValidator;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\TCA\Access;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Events\CreditsUpdated;
use Tokenpass\Events\UserBalanceChanged;
use Tokenpass\Events\UserRegistered;
use Tokenpass\Jobs\PublishPubnubEvent;
use Tokenpass\Jobs\SyncUsersWithTokenChat;
use Tokenpass\Models\Address;
use Tokenpass\Models\TokenChat;
use Tokenpass\Models\User;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Providers\TCAMessenger\TCAMessengerRoster;
use Tokenpass\Repositories\TokenChatRepository;

class TCAMessenger
{

    function __construct(BVAMClient $bvam_client, TCAMessengerAuth $tca_messenger_auth, TCAMessengerRoster $tca_messenger_roster, TCAMessengerActions $tca_messenger_actions, Pubnub $pubnub) {
        $this->bvam_client           = $bvam_client;
        $this->tca_messenger_auth    = $tca_messenger_auth;
        $this->tca_messenger_roster  = $tca_messenger_roster;
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
            $has_access = $this->userIDIsAuthorized($possible_user_id, $tca_stack);
            if ($has_access) {
                // load the user
                $user = $user_repository->findById($possible_user_id);

                // add to the results
                $users[] = $user;
            }
        }

        return $users;

    }

    public function makeSimpleTCAStackFromSerializedInput($serialized_input) {
        $stack = [];
        foreach($serialized_input as $serialized_tca_rule) {
            if (!isset($serialized_tca_rule['quantity']) OR !isset($serialized_tca_rule['token'])) { continue; }
            if (!strlen($serialized_tca_rule['quantity']) AND !strlen($serialized_tca_rule['token'])) { continue; }

            $amount = CurrencyUtil::valueToSatoshis($serialized_tca_rule['quantity']);
            if ($amount <= 0) {
                throw new InvalidArgumentException("Invalid quantity");
            }
            $token = $serialized_tca_rule['token'];
            if (!AssetValidator::isValidAssetName($token)) {
                throw new InvalidArgumentException("Invalid token name");
            }

            $stack[] = [
                'asset'   => $token,
                'amount'  => $amount,
                'op'      => '>=',
                'stackOp' => 'OR',
            ];
        }
        return $stack;
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
        $this->authorizeUserEventChannel($user);
    }

    // --------------------------------
    // Single chat

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
        dispatch(new SyncUsersWithTokenChat($token_chat));
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

            $this->removeUserFromChatIfAdded($user, $token_chat);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }
    }

    public function userAuthorizationInformationForTokenChat(User $user, TokenChat $token_chat) {
        $tca = new Access();
        $balances = Address::getAllUserBalances($user['id'], $filter_disabled = true, $and_provisional = true, $subtract_loans = true);

        $tca_rules = $token_chat['tca_rules'];
        $token_auth = [];
        if ($tca_rules) {
            foreach ($tca_rules as $tca_rule) {
                if ($tca->checkAccess([$tca_rule], $balances)) {
                    $token_auth[] = [
                        'asset'  => $tca_rule['asset'],
                        'amount' => $tca_rule['amount'],
                    ];
                }
            }
        }

        return $token_auth;
    }

    public function userIsAuthorized(User $user, TokenChat $token_chat) {
        if ($token_chat['global']) { return true; }
        return $this->userIDIsAuthorized($user['id'], $token_chat['tca_rules']);
    }

    public function onChatLifecycle(TokenChat $token_chat) {
        if ($token_chat['active']) {
            // authorize the chat
            $this->authorizeChat($token_chat);
        } else {
            // deauthorize the users
            $this->removeChat($token_chat);
        }
    }

    public function onChatDeleted(TokenChat $token_chat) {
        // deauthorize the users
        $this->removeChat($token_chat);
    }


    // ------------------------------------------------------------------------

    public function syncUserToAllChats(User $user) {
        $token_chat_repository = app(TokenChatRepository::class);
        foreach($token_chat_repository->findAll() as $token_chat) {
            $this->syncUserToChat($user, $token_chat);
        }
    }

    public function syncUserToChat(User $user, TokenChat $token_chat) {
        $is_authorized = false;
        if ($token_chat['active']) {
            if ($token_chat['global']) {
                $is_authorized = true;
            } else {
                $tca = new Access();
                $is_authorized = $this->userIDIsAuthorized($user['id'], $token_chat['tca_rules']);
            }
        }

        $channel_name = $token_chat->getChannelName();
        $chat_channel = "chat-{$channel_name}";
        $user_is_already_authorized = $this->tca_messenger_auth->userIsAuthorized($user['id'], $chat_channel);

        if ($is_authorized AND !$user_is_already_authorized) {
            // authorize but don't add
            $this->authorizeUserToChat($user, $token_chat);
        }

        if (!$is_authorized AND $user_is_already_authorized) {
            $this->removeUserFromChatIfAdded($user, $token_chat);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }
    }

    public function authorizeUserToChat(User $user, TokenChat $token_chat) {
        $this->authorizeUser($user);

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

        // send invitation
        $this->tca_messenger_actions->sendChatInvitation($user, $token_chat);
    }

    public function deauthorizeUserFromChat(User $user, TokenChat $token_chat) {
        $channel_name            = $token_chat->getChannelName();
        $chat_channel            = "chat-{$channel_name}";
        $chat_presence_channel   = $chat_channel."-pnpres";
        $chat_identities_channel = "identities-{$channel_name}";

        $auth = $this->tca_messenger_auth;

        $auth->revokeUser($user, $chat_identities_channel);
        $auth->revokeUser($user, $chat_channel);
        $auth->revokeUser($user, $chat_presence_channel);
    }

    public function clearUserGrantCaches(User $user) {
        $token_chat_repository = app(TokenChatRepository::class);
        $auth = $this->tca_messenger_auth;
        foreach($token_chat_repository->findAll() as $token_chat) {
            $channel_name            = $token_chat->getChannelName();
            $chat_channel            = "chat-{$channel_name}";
            $chat_presence_channel   = $chat_channel."-pnpres";
            $chat_identities_channel = "identities-{$channel_name}";

            $auth->clearCacheForUserGrant($user, $chat_identities_channel);
            $auth->clearCacheForUserGrant($user, $chat_channel);
            $auth->clearCacheForUserGrant($user, $chat_presence_channel);
        }
    }

    public function removeChat(TokenChat $token_chat) {
        $auth = $this->tca_messenger_auth;
        $user_repository = app('Tokenpass\Repositories\UserRepository');

        // remove and deauthorize all users
        $chat_channel = "chat-".$token_chat->getChannelName();
        $user_ids = $auth->findUserIDsByChannel($chat_channel)->pluck('user_id');
        foreach($user_ids as $user_id) {
            $user = $user_repository->findById($user_id);

            $this->removeUserFromChatIfAdded($user, $token_chat);
            $this->deauthorizeUserFromChat($user, $token_chat);
        }

    }

    public function addUserToChat(User $user, TokenChat $token_chat, $force=false) {
        DB::transaction(function() use ($user, $token_chat, $force) {
            $is_added = $this->tca_messenger_roster->userIsAddedToChat($user, $token_chat);

            // do not add a user more than once
            if ($is_added AND !$force) { return; }

            // send identity
            $role = 'member';
            if ($token_chat['user_id'] == $user['id']) {
                $role = 'creator';
            }
            $this->tca_messenger_actions->sendIdentity($user, $token_chat, $role);

            // delete first, then add to roster
            if ($is_added) {
                $this->tca_messenger_roster->removeUserFromChat($user, $token_chat);
            }
            $this->tca_messenger_roster->addUserToChat($user, $token_chat, $role);
        });
    }

    public function removeUserFromChatIfAdded(User $user, TokenChat $token_chat) {
        if ($this->tca_messenger_roster->userIsAddedToChat($user, $token_chat)) {
            $this->removeUserFromChat($user, $token_chat);
        }
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

        // remove from roster
        $this->tca_messenger_roster->removeUserFromChat($user, $token_chat);

    }

    // ------------------------------------------------------------------------
    // events
    
    public function subscribe($events)
    {
        $events->listen(UserRegistered::class, 'Tokenpass\Providers\TCAMessenger\TCAMessenger@onUserRegistered');
        $events->listen(AddressBalanceChanged::class, 'Tokenpass\Providers\TCAMessenger\TCAMessenger@onAddressBalanceChanged');
        $events->listen(UserBalanceChanged::class, 'Tokenpass\Providers\TCAMessenger\TCAMessenger@onUserBalanceChanged');
        $events->listen(CreditsUpdated::class, 'Tokenpass\Providers\TCAMessenger\TCAMessenger@onCreditsUpdated');
    }

    public function onUserRegistered(UserRegistered $user_registered) {
        $user = $user_registered->user;

        // authorize control channel
        $this->authorizeUser($user);

        // add all general chats
        $this->syncUserToAllChats($user);
    }


    public function onAddressBalanceChanged(AddressBalanceChanged $address_balance_changed) {
        $address = $address_balance_changed->address;
        $this->syncUserToAllChats($address->user());

        dispatch(new PublishPubnubEvent($address->getUser(), 'tcaUpdated'));

    }

    public function onUserBalanceChanged(UserBalanceChanged $user_balanced_changed) {
        $user = $user_balanced_changed->user;
        $this->syncUserToAllChats($user);

        dispatch(new PublishPubnubEvent($user, 'tcaUpdated'));
    }

    public function onCreditsUpdated(CreditsUpdated $credits_updated) {
        $account = $credits_updated->account;
        $credit_group = $account->appCreditGroup;

        // only publish events for certain credit groups
        if (!$credit_group['publish_events']) { return; }

        // only some accounts belong to a user
        $user = $account->getUser();
        if (!$user) { return null; }

        $slug = $credit_group['event_slug'];
        $balance = $account->calculateBalance();
        dispatch(new PublishPubnubEvent($user, 'creditsUpdated', ['slug' => $slug, 'balance' => $balance,]));
    }


    // ------------------------------------------------------------------------

    protected function userIDIsAuthorized($user_id, $tca_stack) {
        $tca = new Access();
        $balances = Address::getAllUserBalances($user_id, $filter_disabled = true, $and_provisional = true, $subtract_loans = true);
        return $tca->checkAccess($tca_stack, $balances);
    }
    

    protected function authorizeUserControlChannel(User $user) {
        $auth = $this->tca_messenger_auth;
        $user_control_channel = "control-".$user->getChannelName();

        // tokenpass can read/write to user's control channel
        $auth->authorizeTokenpass($read=true, $write=true, $user_control_channel);

        // user can read from control channel
        $auth->authorizeUser($user, $read=true, $write=false, $user_control_channel);

    }

    protected function authorizeUserEventChannel(User $user) {
        $auth = $this->tca_messenger_auth;
        $user_event_channel = "event-".$user->getChannelName();

        // tokenpass can read/write to user's event channel
        $auth->authorizeTokenpass($read=true, $write=true, $user_event_channel);

        // user can read from event channel
        $auth->authorizeUser($user, $read=true, $write=false, $user_event_channel);

    }

}
