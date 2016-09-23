<?php

namespace Tokenpass\Repositories;

use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Exception;

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



}
