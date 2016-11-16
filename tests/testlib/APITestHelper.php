<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Tokenly\HmacAuth\Generator;
use Tokenpass\Models\User;
use \PHPUnit_Framework_Assert as PHPUnit;

/**
*  APITestHelper
*/
class APITestHelper
{

    public $url_base = '';

    protected $user = null;

    function __construct(Application $app) {
        $this->app = $app;
    }

    public function beNoOne()
    {
        $this->user = null;
        return $this;
    }
    
    
    public function be(User $user)
    {
        $this->user = $user;
        return $this;
    }
    
    public function callAPIWithoutAuthenticationAndReturnJSONContent($method, $url, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        $request = $this->createAPIRequest($method, $url, $parameters, $cookies, $files, $server, $content);
        $response = $this->runRequest($request);
        PHPUnit::assertEquals($expected_response_code, $response->getStatusCode(), "Response was: ".$response->getContent());
        return json_decode($response->getContent(), true);
    }

    
    public function callJSON($method, $uri_or_url_extension, $parameters=[], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null)
    {
        return $this->callAPIAndReturnJSONContent($method, $uri_or_url_extension, $parameters, $expected_response_code, $cookies, $files, $server, $content);
    }

    public function callAPIAndReturnJSONContent($method, $uri_or_url_extension, $parameters=[], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        if (substr($uri_or_url_extension, 0, 1) == '/' OR substr($uri_or_url_extension, 0, 7) == 'http://' OR substr($uri_or_url_extension, 0, 8) == 'https://' ) {
            $uri = $uri_or_url_extension;
        } else {
            $uri = $this->extendURL($this->url_base, $uri_or_url_extension);
        }
        $request = $this->createAPIRequest($method, $uri, $parameters, $cookies, $files, $server, $content);

        if ($this->user) {
            $generator = new Generator();
            $api_token = $this->user['apitoken'];
            $secret = $this->user['apisecretkey'];
            $generator->addSignatureToSymfonyRequest($request, $api_token, $secret);
        }
        
        $response = $this->runRequest($request);

        PHPUnit::assertEquals($expected_response_code, $response->getStatusCode(), "Response was: ".$response->getContent());

        return json_decode($response->getContent(), true);
    }

    protected function createAPIRequest($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null) {
        // convert a POST to json
        if ($parameters AND $method == 'POST' OR $method == 'PATCH' OR $method == 'PUT') {
            $content = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
            $server['CONTENT_TYPE'] = 'application/json';
            $parameters = [];
        }

        // always want JSON
        $server['HTTP_ACCEPT'] = 'application/json';

        return Request::create($uri, $method, $parameters, $cookies, $files, $server, $content);
    }


    ////////////////////////////////////////////////////////////////////////
    

    protected function extendURL($base_url, $url_extension) {
        return $base_url.(strlen($url_extension) ? '/'.ltrim($url_extension, '/') : '');
    }

    protected function runRequest($request) {
        $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return $response;
    }


}