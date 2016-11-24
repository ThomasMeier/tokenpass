<?php

namespace Tokenpass\Models;

use Tokenly\LaravelApiProvider\Model\APIModel;
use Exception;

class TokenChat extends APIModel {

    protected $api_attributes = ['id',];

    protected $casts = [
        'tca_rules' => 'json',
        'active'    => 'boolean',
    ];

}
