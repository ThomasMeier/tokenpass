<?php

class Client {

    private $app_id;
    private $api_key;
    private $api_secret;

    function __construct($app_id, $api_key, $api_secret) {
        $this->app_id = $app_id;
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    private function makeAuthorizationHeader($targetPath, $targetMethod, $requestBody) {
        /*
        $jwtToken = jwtjs.createToken(config.appId, hostedServices['SIPHostedService'].base_url, config.appId, JWT_EXPIRATION, {
      method: targetMethod,
      path: targetPath
    }, config.prvKey);

    const extension = jwtjs.createCivicExt(requestBody, config.appSecret);
    return 'Civic' + ' ' + jwtToken + '.' + extension; */
  }

    function exchangeCode($jwtToken) {
        $body = array( 'authToken' => $jwtToken );
    }
}