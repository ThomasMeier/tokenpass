<?php

namespace Tokenpass\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Util\EthereumUtil;
/*
* AddressRepository
*/
class AddressRepository extends APIRepository
{

    protected $model_type = 'Tokenpass\Models\Address';


    public function findByReceiveMonitorID($monitor_id) {
        return $this->prototype_model->where('receive_monitor_id', $monitor_id)->first();
    }

    public function findBySendMonitorID($monitor_id) {
        return $this->prototype_model->where('send_monitor_id', $monitor_id)->first();
    }

    public function findAllByUserID($user_id)
    {
        return $this->prototype_model->where('user_id', $user_id)->get();
    }

    public function getPseudoAddressForUser(User $user) {
        return $this->getPseudoAddressForUserID($user['id']);
    }

    public function getPseudoAddressForUserID($user_id) {
        return $this->prototype_model
            ->where('user_id', $user_id)
            ->where('pseudo', 1)
            ->first();
    }


    // finds the verified address model by bitcoin address
    public function findVerifiedByAddress($address) {
        return Address::where('address', $address)->where('verified', 1)->first();
    }

    // balances loaned to pseudo addresses are always public
    public function getCombinedAddressBalancesByUser($user_id, $public_only=true, $active_only=true, $verified_only=true) {
        $balance_query = DB::Table('address_balances')->whereIn('address_id', function($query) use ($user_id, $public_only, $active_only, $verified_only) {
            $query->select('id')->from('coin_addresses')->where('user_id', '=', $user_id);

            if ($public_only) {
                $query->where(function($query) {
                    $query->where('public', '=', 1);
                    $query->orWhere('pseudo', '=', 1);
                });
            }
            if ($active_only) {
                $query->where('active_toggle', '=', 1);
            }
            if ($verified_only) {
                $query->where('verified', 1);
            }
        });

        $balance_query
            ->select('asset', DB::raw('SUM(balance) as balance'))
            ->groupBy('asset');

        return $balance_query->get();
    }

    public function getCombinedPromisedBalancesByUser($user_id, $public_only=true, $active_only=true, $verified_only=true) {
        $balance_query = DB::Table('provisional_tca_txs')->whereIn('destination', function($query) use ($user_id, $public_only, $active_only, $verified_only) {
            $query->select('address')->from('coin_addresses')->where('user_id', '=', $user_id);

            if ($public_only) {
                $query->where(function($query) {
                    $query->where('public', '=', 1);
                    $query->orWhere('pseudo', '=', 1);
                });
            }
            if ($active_only) {
                $query->where('active_toggle', '=', 1);
            }
            if ($verified_only) {
                $query->where('verified', 1);
            }
        });

        $balance_query
            ->select('asset', DB::raw('SUM(quantity) as balance'))
            ->groupBy('asset');

        return $balance_query->get();
    }


    public function getCombinedLoanedBalancesByUser($user_id, $public_only=true, $active_only=true, $verified_only=true) {
        $balance_query = DB::Table('provisional_tca_txs')->whereIn('source', function($query) use ($user_id, $public_only, $active_only, $verified_only) {
            $query->select('address')->from('coin_addresses')->where('user_id', '=', $user_id);

            if ($public_only) {
                $query->where(function($query) {
                    $query->where('public', '=', 1);
                    $query->orWhere('pseudo', '=', 1);
                });
            }
            if ($active_only) {
                $query->where('active_toggle', '=', 1);
            }
            if ($verified_only) {
                $query->where('verified', 1);
            }
        });

        $balance_query
            ->select('asset', DB::raw('SUM(quantity) as balance'))
            ->groupBy('asset');

        return $balance_query->get();
    }



    public static function updateUserBalances($user_id)
    {
        $xchain = app('Tokenly\XChainClient\Client');

        $address_list = Address::where('user_id', $user_id)->where('verified', '=', 1)->get();
        if(!$address_list OR count($address_list) == 0){
            return false;
        }
        $stamp = date('Y-m-d H:i:s');
        foreach($address_list as $address_model){
            // never sync pseudo addresses
            if (!$address_model->isPseudoAddress()) {
                if (!$address_model->isEther()) {
                    $balances = $xchain->getBalances($address_model->address, true);
                    Log::debug("{$address_model->address} \$balances=".json_encode($balances, 192));
                    if($balances AND count($balances) > 0){
                        $update = Address::updateAddressBalances($address_model->id, $balances);
                        if(!$update){
                            return false;
                        }
                    }
                } else {
                    $ethereum = new EthereumUtil();
                    $balance = EthereumUtil::hexdec_0x($ethereum->checkBalance($address_model->address));
                    if (Address::updateAddressBalances($address_model->id, $balance)) {
                        return false;
                    }
                }
            }

            $address_model->invalidateOverdrawnPromises();

            // fire an address balanced changed event
            Event::fire(new AddressBalanceChanged($address_model));
        }
        return true;

    }

    // find all user IDs that have at least one of any of the tokens listed
    public function findUserIDsWithToken($tokens, $public_only=false, $active_only=true, $verified_only=true) {
        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }

        $query = DB::Table('coin_addresses')
            ->join('address_balances', 'coin_addresses.id', '=', 'address_balances.address_id')
            ->select('coin_addresses.user_id')
            ->whereIn('address_balances.asset', $tokens)
            ->where('address_balances.balance', '>', 0);

        if ($public_only) {
            $query->where('coin_addresses.public', '=', 1);
        }
        if ($active_only) {
            $query->where('coin_addresses.active_toggle', '=', 1);
        }
        if ($verified_only) {
            $query->where('coin_addresses.verified', '=', 1);
        }

        $query->groupBy('user_id');

        return $query->get()->pluck('user_id');
    }


}
