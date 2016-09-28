<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use \PHPUnit_Framework_Assert as PHPUnit;

/**
*  APITestHelper
*/
class APITestHelper
{

    public $url_base = '';

    function __construct(Application $app) {
        $this->app = $app;
    }

    public function callAPIWithoutAuthenticationAndReturnJSONContent($method, $url, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        $request = $this->createAPIRequest($method, $url, $parameters, $cookies, $files, $server, $content);
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