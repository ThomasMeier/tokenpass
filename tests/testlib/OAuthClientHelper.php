<?php

use Illuminate\Support\Facades\Log;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* OAuthClientHelper
*/
class OAuthClientHelper
{
    static $OFFSET = 0;

    public function __construct() {
    }

    public function createRandomOAuthClient($override_vars=[]) {
        return $this->createSampleOAuthClient($this->getRandomOAuthClientVars());
    }

    public function getRandomOAuthClientVars() {
        ++self::$OFFSET;
        return [
            'id'   => 'APITOKEN_'.sprintf('%03d', self::$OFFSET),
            'name' => 'client '.sprintf('%03d', self::$OFFSET),
        ];
    }

    public function createSampleOAuthClient($override_vars=[]) {
        // create an oauth client
        $oauth_client = app('Tokenpass\Repositories\OAuthClientRepository')->create(array_merge([
            'id'     => 'MY_API_TOKEN',
            'secret' => 'MY_SECRET',
            'name'   => 'client one',
        ], $override_vars));

        return $oauth_client;
    }


}
