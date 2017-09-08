<?php

use Lcobucci\JWT\Builder;

class Client {

    private $app_id;
    private $api_key;
    private $api_secret;
    private $hostedServices;


    private $invokeUrl;
    private $endpoint;
    private $pathComponent;

    // extract endpoint and path from url
    const invokeUrl = hostedServices.SIPHostedService.base_url + config.env;
    const endpoint = /(^https?:\/\/[^\/]+)/g.exec(invokeUrl)[1];
  const pathComponent = invokeUrl.substring(endpoint.length);

    function __construct($app_id, $api_key, $api_secret) {
        $this->app_id = $app_id;
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;

        $this->hostedServices = array(
            'SIPHostedService' => array(
                'base_url' => 'https://api.civic.com/sip/',
                'hexpub' => '049a45998638cfb3c4b211d72030d9ae8329a242db63bfb0076a54e7647370a8ac5708b57af6065805d5a6be72332620932dbb35e8d318fce18e7c980a0eb26aa1',
                'tokenType' => 'JWT'
            )
        );

        $this->invokeUrl = $this->hostedServices['SIPHostedService']['base_url'] . 'prod';
        $this->endpoint = preg_match("/(^https?:\/\/[^\/]+)/g", $this->invokeUrl)[1];
        $this->pathComponent = substr($this->invokeUrl, strlen($this->endpoint));
    }

    private function makeAuthorizationHeader($targetPath, $targetMethod, $requestBody) {
        $payload = array('method' => $targetMethod, 'path' => $targetPath);
        $jwtToken = (new Builder())->setIssuer($this->app_id)
        ->setAudience($this->hostedServices['SIPHostedService']['base_url'])
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
        $contentLength = mb_strlen(json_encode($body), 'utf8');

        $additionalParams = array(
            'headers' => array(
                'Content-Length' => $contentLength,
                'Accept' => '*/*',
                'Authorization' => $authHeader
            ),
            'queryParams' => array()
        );

        $params = array();

        $scopeRequestAuthCodePostRequest = array(
            'verb' => 'POST',
            'path' => $this->pathComponent

        );
        const scopeRequestAuthCodePostRequest = {
            verb: 'post'.toUpperCase(),
        path: pathComponent + uritemplate('/scopeRequest/authCode').expand(apiGateway.core.utils.parseParametersToObject(params, [])),
        headers: apiGateway.core.utils.parseParametersToObject(params, []),
        queryParams: apiGateway.core.utils.parseParametersToObject(params, []),
        body: body
    };
    }

    function verifyAndDecrypt($payload) {
        $token = $payload['data'];
        echo $token;
    }
}