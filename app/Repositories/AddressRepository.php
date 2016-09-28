<?php

namespace Tokenpass\Repositories;

use Exception;
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
