<?php

use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\User;
use \PHPUnit_Framework_Assert as PHPUnit;

/**
*  OauthUserAPITester
*/
class OauthUserAPITester
{

    var $token = null;

    function __construct($token=null) {
        if ($token !== null) { $this->setToken($token); }
        $this->api_test_helper = app('APITestHelper');
    }

    public function setToken($token) {
        $this->token = $token;
        return $this;
    }


    public function expectUnauthenticatedResponse($method, $route_spec, $parameters=[]) {
        $expected_response_code = 403;
        return $this->callAPIAndReturnJSONContent($method, $route_spec, $parameters, $expected_response_code);
    }

    public function expectAuthenticatedResponse($method, $route_spec, $parameters=[], $expected_response_code=200) {
        return $this->callAPIAndReturnJSONContent($method, $route_spec, $parameters, $expected_response_code);
    }

    public function callJSON($method, $route_spec, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        return $this->callAPIAndReturnJSONContent($method, $route_spec, $parameters, $expected_response_code, $cookies, $files, $server, $content);
    }

    public function callAPIAndReturnJSONContent($method, $route_spec, $parameters = [], $expected_response_code=200, $cookies = [], $files = [], $server = [], $content = null) {
        if ($this->token) { $parameters['oauth_token'] = $this->token; }
        $url = $this->resolveRouteSpec($route_spec);
        return $this->api_test_helper->callAPIWithoutAuthenticationAndReturnJSONContent($method, $url, $parameters, $expected_response_code);
    }

    public function testErrors($error_scenarios, $defaults=[]) {
        foreach($error_scenarios as $error_scenario) {
            $expected_response_code = isset($error_scenario['expectedResponseCode']) ? $error_scenario['expectedResponseCode'] : ($defaults['expectedResponseCode'] ? $defaults['expectedResponseCode'] : 422);
            $expected_error_string  = isset($error_scenario['expectedErrorString'])  ? $error_scenario['expectedErrorString']  : null;
            if (isset($error_scenario['valid']) AND $error_scenario['valid']) {
                $expected_response_code = 200;
            }
            $post_vars = array_merge(isset($defaults['postVars']) ? $defaults['postVars'] : [], isset($error_scenario['postVars']) ? $error_scenario['postVars'] : []);
            $this->runErrorScenario(isset($error_scenario['method']) ? $error_scenario['method'] : $defaults['method'], isset($error_scenario['route']) ? $error_scenario['route'] : $defaults['route'], $post_vars, $expected_error_string, $expected_response_code);

        }
    }

    // ------------------------------------------------------------------------

    protected function runErrorScenario($method, $route, $posted_vars, $expected_error, $expected_response_code=422) {
        $response_data = $this->expectAuthenticatedResponse($method, $this->resolveRouteSpec($route), $posted_vars, $expected_response_code);
        if ($expected_response_code == 200) { return; }
        PHPUnit::assertContains($expected_error, $response_data['errors'][0], "Failed validation for route $method ".$this->resolveRouteSpec($route)." ".json_encode($posted_vars));
    }
    
    protected function resolveRouteSpec($route_spec) {
        if (is_array($route_spec)) {
            list($route_name, $route_params) = $route_spec;
        } else {
            // check for already resolved URL
            if (strpos($route_spec, '/') !== false) {
                return $route_spec;
            }

            $route_name = $route_spec;
            $route_params = [];

        }
        $url = route($route_name, $route_params);
        return $url;
    }

}