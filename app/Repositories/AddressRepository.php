<?php

namespace Tokenpass\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Tokenpass\Models\Address;

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

    public function getCombinedAddressBalancesByUser($user_id, $public_only=true, $active_only=true, $verified_only=true) {
        $balance_query = DB::Table('address_balances')->whereIn('address_id', function($query) use ($user_id, $public_only, $active_only, $verified_only) {
            $query->select('id')->from('coin_addresses')->where('user_id', '=', $user_id);

            if ($public_only) {
                $query->where('public', '=', 1);
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

    public static function updateUserBalances($user_id)
    {
        $xchain = app('Tokenly\XChainClient\Client');

        $address_list = Address::where('user_id', $user_id)->where('verified', '=', 1)->get();
        if(!$address_list OR count($address_list) == 0){
            return false;
        }
        $stamp = date('Y-m-d H:i:s');
        foreach($address_list as $address_model){
            $balances = $xchain->getBalances($address_model->address, true);
            if($balances AND count($balances) > 0){
                $update = Address::updateAddressBalances($address_model->id, $balances);
                if(!$update){
                    return false;
                }
            }
            $address_model->invalidateOverdrawnPromises();
        }
        return true;        
        
    }


}
