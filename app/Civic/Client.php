<?php

use Lcobucci\JWT\Builder;

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
        $payload = array('method' => $targetMethod, 'path' => $targetPath);
        $jwtToken = (new Builder())->setIssuer($this->app_id)
        ->setAudience($hostedServices['SIPHostedService']->base_url)
        ->setId( \Ramsey\Uuid\Uuid::uuid4(), true) // Configures the id (jti claim), replicating as a header item
        ->setIssuedAt(time()) // Configures the time that the token was issue (iat claim)
        ->setNotBefore(time() + 60) // Configures the time that the token can be used (nbf claim)
        ->setExpiration(time() + 3600) // Configures the expiration time of the token (exp claim)
        ->setSubject($this->app_id)
        ->set('data', $payload)
        ->getToken(); // Retrieves the generated token

        $extension = JWT::createCivicExt($requestBody, $this->api_secret);

        return 'Civic' . ' ' . $jwtToken . '.' . $extension;
    }

    function exchangeCode($jwtToken) {
        $body = array( 'authToken' => $jwtToken );
        $authHeader = $this->makeAuthorizationHeader('scopeRequest/authCode', 'POST', body);
    }

    function verifyAndDecrypt($payload) {
        $token = $payload['data'];
        echo $token;
    }
}