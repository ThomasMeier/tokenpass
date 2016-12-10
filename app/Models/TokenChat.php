<?php

namespace Tokenpass\Models;

use Exception;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenly\LaravelApiProvider\Model\APIModel;
use Tokenpass\Models\User;

class TokenChat extends APIModel {

    protected $api_attributes = ['id',];

    protected $casts = [
        'tca_rules' => 'json',
        'active'    => 'boolean',
        'global'    => 'boolean',
    ];

    public function getChannelName() {
        if (!isset($this->_channel_name)) {
            $base58 = new Base58(null, new GMPService());
            $this->_channel_name = $base58->encode(hex2bin(str_replace('-', '', $this['uuid'])));
        }
        return $this->_channel_name;
    }

    public function forceChannelName($channel_name) {
        $this->_channel_name = $channel_name;
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
