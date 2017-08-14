<?php

namespace Tokenpass\TokenDelivery;

class DeliveryClient extends \Tokenly\DeliveryClient\Client {

    function updateEmailTx() {
        $data = array();
        return $this->newAPIRequest('POST', '/fulfillment/multiple/', $data);
    }

}