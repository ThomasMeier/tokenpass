<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use Pubnub\Pubnub;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\TCA\Access;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;

class TCAMessenger
{

    function __construct(BVAMClient $bvam_client, Pubnub $pubnub) {
        $this->bvam_client = $bvam_client;
        $this->pubnub      = $pubnub;
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

    public function broadcast($quantity, $token, $message_contents) {
        $tca_stack = [
            [
                'asset'   => $token,
                'amount'  => CurrencyUtil::valueToSatoshis($quantity),
                'op'      => '>=',
                'stackOp' => 'AND',
            ]
        ];

        $count = 0;
        $recipient_users = $this->findUsersWithTokens($tca_stack);
        foreach($recipient_users as $user) {
            $message = [
                'quantity' => $quantity,
                'token'    => $token,
                'msg'      => $message_contents,
            ];

            try {
                $this->pubnub->publish($user->getChannelName(), $message);
                ++$count;
            } catch (Exception $e) {
                EventLog::logError('pubnub.publishFailed', $e, ['user' => $user['id']]);
            }

        }

        EventLog::info('pubnub.publish', ['quantity' => $quantity, 'token' => $token, 'count' => $count,]);
        return $count;
    }

}
