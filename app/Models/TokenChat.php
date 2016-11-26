<?php

namespace Tokenpass\Models;

use Exception;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenly\LaravelApiProvider\Model\APIModel;

class TokenChat extends APIModel {

    protected $api_attributes = ['id',];

    protected $casts = [
        'tca_rules' => 'json',
        'active'    => 'boolean',
    ];

    public function getChannelName() {
        $base58 = new Base58(null, new GMPService());
        return $base58->encode(hex2bin(str_replace('-', '', $this['uuid'])));
    }
}
