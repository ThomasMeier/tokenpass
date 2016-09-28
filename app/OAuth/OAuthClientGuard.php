<?php

namespace Tokenpass\OAuth;


use Exception;
use Illuminate\Support\Facades\Log;
use Tokenpass\Models\OAuthClient;

class OAuthClientGuard {

    protected $oauth_client;

    function __construct() {
    }

    public function setOAuthClient(OAuthClient $oauth_client) {
        $this->oauth_client = $oauth_client;
    }

    public function oauthClient() {
        return $this->oauth_client;
    }


}
