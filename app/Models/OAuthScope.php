<?php

namespace Tokenpass\Models;

use Tokenly\LaravelApiProvider\Model\APIModel;
use Exception;

class OAuthScope extends APIModel {

    protected $table = 'oauth_scopes';

    public $incrementing = false;

    protected $api_attributes = ['id','description', 'label', 'notice_level'];

}
