<?php

namespace Tokenpass\Models;

use Exception;
use StephenHill\Base58;
use StephenHill\GMPService;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\LaravelApiProvider\Model\APIModel;
use Tokenpass\Models\User;

class TokenChat extends APIModel {

    protected $api_attributes = ['id','name','active','global','tokens',];

    protected $casts = [
        'tca_rules' => 'json',
        'active'    => 'boolean',
        'global'    => 'boolean',
    ];

    public static function channelNameToUuid($channel_name) {
        $base58 = new Base58(null, new GMPService());
        $decoded_uuid = bin2hex($base58->decode($channel_name));
        return substr($decoded_uuid, 0, 8).'-'.substr($decoded_uuid, 8, 4).'-'.substr($decoded_uuid, 12, 4).'-'.substr($decoded_uuid, 16, 4).'-'.substr($decoded_uuid, 20, 12);
    }

    public function getChatID() {
        return $this->getChannelName();
    }

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

    public function getTokensAttribute() {
        $tokens = [];

        // insert rules if active
        $tca_rules = $this['tca_rules'];
        if ($tca_rules) {
            foreach ($tca_rules as $tca_rule) {
                if (strlen($tca_rule['asset'])) {
                    $tokens[$tca_rule['asset']] = CurrencyUtil::satoshisToValue($tca_rule['amount']);
                }
            }
        }

        return $tokens;
    }
}
