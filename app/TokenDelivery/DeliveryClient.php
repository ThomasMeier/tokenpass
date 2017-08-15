<?php

namespace Tokenpass\TokenDelivery;

use Tokenly\HmacAuth\Generator;

class DeliveryClient extends \Tokenly\DeliveryClient\Client {

    function __construct($api_url=null, $api_token=null, $api_secret_key=null)
    {
        if(empty($api_url)) {
            $api_url = env('TOKENDELIVERY_CONNECTION_URL');
        }
        if(empty($api_token)) {
            $api_token = env('TOKENDELIVERY_API_TOKEN');
        }
        if($api_secret_key) {
            $api_secret_key = env('TOKENDELIVERY_API_KEY');
        }
        parent::__construct($api_url, $api_token, $api_secret_key);
    }

    function updateEmailTx($username, $email) {
        $data = array(
            'username' => $username,
            'email'    => $email
        );
        return $this->newAPIRequest('POST', '/v1/email_deliveries/update', $data);
    }

}