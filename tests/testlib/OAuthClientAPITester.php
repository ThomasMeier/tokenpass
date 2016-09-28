<?php

use Illuminate\Http\Request;
use Tokenly\HmacAuth\Generator;
use Tokenpass\Models\OAuthClient;
use \PHPUnit_Framework_Assert as PHPUnit;

/**
*  OAuthClientAPITester
*  Test API resource requests
*/
class OAuthClientAPITester
{

    public $url_base = '';

    function __construct($resource_repository=null) {
        $this->resource_repository = $resource_repository;
    }

    public function be(OAuthClient $oauth_client) {
        $this->oauth_client = $oauth_client;
        return $this;
    }

    public function testRequireAuth($method='POST', $url) {
        $parameters = [];
        $cookies = [];
        $files = [];
        $server = [];
        $content = null;

        $request = $this->createAPIRequest($method, $url, $parameters, $cookies, $files, $server, $content);

        // expect to get a 403
        $response = $this->runRequest($request);
        PHPUnit::assertEquals(403, $response->getStatusCode(), "Expected 403 Unauthenticated response.  Got ".$response->getStatusCode()." for $url instead.");
        return json_decode($response->getContent(), true);
    }

    public function testAddResource($posted_vars, $expected_created_resource, $url_extension=null)
    {
        // call the API
        $response = $this->callAPIWithAuthentication('POST', $this->extendURL($this->url_base, $url_extension), $posted_vars);
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent()."\n\nfor POST ".$this->extendURL($this->url_base, $url_extension));
        $response_from_api = json_decode($response->getContent(), 1);

        // populate the $expected_created_resource
        $expected_created_resource = $this->fillExpectedResourceWithAPIRespose($expected_created_resource, $response_from_api);

        // check response
        PHPUnit::assertNotEmpty($response_from_api);
        PHPUnit::assertEquals($expected_created_resource, $response_from_api);

        // load from repository
        if (!$this->resource_repository) { throw new Exception("resource_repository not defined", 1); }
        $loaded_resource_model = $this->resource_repository->findByUuid($response_from_api['id']);
        PHPUnit::assertNotEmpty($loaded_resource_model);
        PHPUnit::assertEquals($expected_created_resource, $loaded_resource_model->serializeForAPI());

        // return the loaded resource
        return $loaded_resource_model;
    }

    public function testUpdateErrors($created_resource, $error_scenarios)
    {
        return $this->testErrors($error_scenarios, [
            'method'  => 'PATCH',
            'urlPath' => '/'.$created_resource['uuid'],
        ]);
    }

    public function testAddErrors($error_scenarios, $url_path = '')
    {
        return $this->testErrors($error_scenarios, [
            'method'  => 'POST',
            'urlPath' => $url_path,
        ]);
    }

    public function testGetErrors($error_scenarios, $url_path = '')
    {
        return $this->testErrors($error_scenarios, [
            'method'  => 'GET',
            'urlPath' => $url_path,
        ]);
    }

    public function testErrors($error_scenarios, $defaults=[])
    {
        foreach($error_scenarios as $error_scenario) {
            $this->runErrorScenario(isset($error_scenario['method']) ? $error_scenario['method'] : $defaults['method'], isset($error_scenario['urlPath']) ? $error_scenario['urlPath'] : $defaults['urlPath'], $error_scenario['postVars'], $error_scenario['expectedErrorString']);
        }
    }


    public function testListResources($actual_created_sample_resources, $url_extension=null) {
        $expected_created_resources_response = [];
        foreach($actual_created_sample_resources as $resource_model) {
            $expected_created_resources_response[] = $resource_model->serializeForAPI();
        }

        // echo "url:\n".json_encode($this->url_base.$url_extension, 192)."\n";
        $response = $this->callAPIWithAuthentication('GET', $this->extendURL($this->url_base, $url_extension));
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent());
        $loaded_resources_from_api = json_decode($response->getContent(), 1);
        PHPUnit::assertEquals($expected_created_resources_response, $loaded_resources_from_api);

        return $loaded_resources_from_api;
    }

    public function testGetResource($actual_created_sample_resource) {
        $response = $this->callAPIWithAuthentication('GET', $this->url_base.'/'.$actual_created_sample_resource['uuid']);
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent());
        
        $loaded_resource_from_api = json_decode($response->getContent(), 1);
        PHPUnit::assertEquals($actual_created_sample_resource->serializeForAPI(), $loaded_resource_from_api);

        return $loaded_resource_from_api;
    }

    public function testUpdateResource($created_resource, $update_vars) {
        $response = $this->callAPIWithAuthentication('PATCH', $this->url_base.'/'.$created_resource['uuid'], $update_vars);
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent());
        $loaded_resource_from_api = json_decode($response->getContent(), 1);
        PHPUnit::assertNotEmpty($loaded_resource_from_api);

        $response = $this->callAPIWithAuthentication('GET', $this->url_base.'/'.$created_resource['uuid']);
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent());
        $reloaded_resource_from_api = json_decode($response->getContent(), 1);
        PHPUnit::assertEquals($created_resource['uuid'], $reloaded_resource_from_api['id']);

        foreach($update_vars as $k => $v) {
            PHPUnit::assertEquals($update_vars[$k], $loaded_resource_from_api[$k]);
            PHPUnit::assertEquals($update_vars[$k], $reloaded_resource_from_api[$k]);
        }

        return $loaded_resource_from_api;
    }

    public function testDeleteResource($created_resource) {
        // get the resource successfully
        $response = $this->callAPIWithAuthentication('GET', $this->url_base.'/'.$created_resource['uuid']);
        PHPUnit::assertEquals(200, $response->getStatusCode(), "Response was: ".$response->getContent());

        // delete the resource
        $response = $this->callAPIWithAuthentication('DELETE', $this->url_base.'/'.$created_resource['uuid']);
        PHPUnit::assertEquals(204, $response->getStatusCode(), "Response was: ".$response->getContent());

        // now try to get it and get a 404
        $response = $this->callAPIWithAuthentication('GET', $this->url_base.'/'.$created_resource['uuid']);
        PHPUnit::assertEquals(404, $response->getStatusCode(), "Response was: ".$response->getContent());
    }



    public function callAPIWithAuthenticationAndReturnJSONContent($method, $url, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        $response = $this->callAPIWithAuthentication($method, $url, $parameters, $cookies, $files, $server, $content);
        PHPUnit::assertEquals($expected_response_code, $response->getStatusCode(), "Response was: ".$response->getContent());
        return json_decode($response->getContent(), true);
    }

    public function callAPIWithoutAuthenticationAndReturnJSONContent($method, $url, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        $request = $this->createAPIRequest($method, $url, $parameters, $cookies, $files, $server, $content);
        $response = $this->runRequest($request);
        PHPUnit::assertEquals($expected_response_code, $response->getStatusCode(), "Response was: ".$response->getContent());
        return json_decode($response->getContent(), true);
    }

    public function callAPIWithAuthentication($method, $url, $parameters = [], $cookies = [], $files = [], $server = [], $content = null) {
        $request = $this->createAPIRequest($method, $url, $parameters, $cookies, $files, $server, $content);
        $generator = new Generator();

        if (isset($this->oauth_client)) {
            $api_token      = $this->oauth_client['id'];
            $api_secret_key = $this->oauth_client['secret'];
        } else {
            $api_token      = 'TESTAPITOKEN';
            $api_secret_key = 'TESTAPISECRET';
        }

        $generator->addSignatureToSymfonyRequest($request, $api_token, $api_secret_key);
        return $this->runRequest($request);
    }

    protected function createAPIRequest($method, $url, $parameters = [], $cookies = [], $files = [], $server = [], $content = null) {
        // convert a POST to json
        if ($parameters AND $method == 'POST' OR $method == 'PATCH' OR $method == 'PUT') {
            $content = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
            $server['CONTENT_TYPE'] = 'application/json';
            $parameters = [];
        }

        // always want JSON
        $server['HTTP_ACCEPT'] = 'application/json';

        return Request::create($url, $method, $parameters, $cookies, $files, $server, $content);
    }


    ////////////////////////////////////////////////////////////////////////
    
    protected function runErrorScenario($method, $url_path, $posted_vars, $expected_error) {
        $response = $this->callAPIWithAuthentication($method, $this->url_base.$url_path, $posted_vars);
        PHPUnit::assertEquals(400, $response->getStatusCode(), "Response was: ".$response->getContent());
        $response_data = json_decode($response->getContent(), true);
        PHPUnit::assertContains($expected_error, $response_data['errors'][0]);
    }

    protected function fillExpectedResourceWithAPIRespose($expected_created_resource, $response_from_api) {
        foreach($expected_created_resource as $k => $v) {
            if (preg_match('!\{\{response\.(.*)\}\}!', $v, $matches)) {
                $value = isset($response_from_api[$matches[1]]) ? $response_from_api[$matches[1]] : '--- NULL ---';
                $expected_created_resource[$k] = $value;
            }
        }

        return $expected_created_resource;
    }

    protected function extendURL($base_url, $url_extension) {
        return $base_url.(strlen($url_extension) ? '/'.ltrim($url_extension, '/') : '');
    }

    protected function runRequest($request) {
        $kernel = app('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return $response;
    }


}