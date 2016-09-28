<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* APIControllerAddressesTest
*/
class APIControllerAddressesTest extends TestCase {

    protected $use_database = true;

    public function testRequireOauthTokenAddressControllerMethods() {
        $user_helper = app('UserHelper');
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();

        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('POST', route('api.tca.address.new'));
        PHPUnit::assertContains('No access token provided', $response['message']);
        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('PATCH', route('api.tca.address.edit', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']));
        PHPUnit::assertContains('No access token provided', $response['message']);
        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('POST', route('api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']));
        PHPUnit::assertContains('No access token provided', $response['message']);
        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('DELETE', route('api.tca.address.delete', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']));
        PHPUnit::assertContains('No access token provided', $response['message']);
    }

    public function testRequireManageScopeForTokenAddressControllerMethods() {
        $user_helper = app('UserHelper');
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession([], ['tca']);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.new'), [], 403);
        PHPUnit::assertContains('scopes are not authorized', $response['errors'][0]);
        $response = $api_tester->expectAuthenticatedResponse('PATCH', route('api.tca.address.edit', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), [], 403);
        PHPUnit::assertContains('scopes are not authorized', $response['errors'][0]);
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), [], 403);
        PHPUnit::assertContains('scopes are not authorized', $response['errors'][0]);
        $response = $api_tester->expectAuthenticatedResponse('DELETE', route('api.tca.address.delete', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), [], 403);
        PHPUnit::assertContains('scopes are not authorized', $response['errors'][0]);
    }

    public function testRegisterAddress() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // require authentication
        $response = app('OauthUserAPITester')->expectUnauthenticatedResponse('POST', route('api.tca.address.new'));
        PHPUnit::assertContains('No access token provided', $response['message']);

        // Valid
        $this->buildXChainMock();
        $vars = [];
        $vars['address'] = '1NLwKTJVa5VMvaP62hNaPt3ddbpXLBE9Ug';
        $vars['type'] = 'btc';
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.new'), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals('1NLwKTJVa5VMvaP62hNaPt3ddbpXLBE9Ug', $response['result']['address']);
    }

    public function testErrorsForRegisterAddress() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // run validation tests
        $error_scenario_defaults = [
            'method'               => 'POST',
            'route'                => 'api.tca.address.new',
            'postVars'             => ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'],
            'expectedResponseCode' => 422,
            'expectedErrorString'  => 'null',
            'valid'                => false,
        ];
        $api_tester->testErrors([
            [
                'postVars'            => ['type' => 'foobar'],
                'expectedErrorString' => 'selected type is invalid',
            ],
            [
                'postVars'            => ['address' => ''],
                'expectedErrorString' => 'address field is required',
            ],
            [
                'postVars'            => ['address' => 'ImSoBAD'],
                'expectedErrorString' => 'must be a valid address',
            ],
            [
                'postVars'            => ['label' => str_repeat('x', 256)],
                'expectedErrorString' => 'may not be greater than 255 characters',
            ],
            [
                'postVars'            => ['public' => 'foobar'],
                'expectedErrorString' => 'must be true or false',
            ],
            [
                'postVars'            => ['active' => 'foobar'],
                'expectedErrorString' => 'must be true or false',
            ],
            [
                'postVars'            => ['public' => true, 'address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU'],
                'valid'               => true,
            ],
            [
                'postVars'            => ['active' => '0', 'address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v'],
                'valid'               => true,
            ],
        ], $error_scenario_defaults);
    }

    public function testUpdateRegisteredAddress() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // add address
        $this->buildXChainMock();
        $vars = ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.new'), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);

        // update address
        $vars = ['label' => 'NEW Label', 'public' => false, 'active' => false];
        $response = $api_tester->expectAuthenticatedResponse('PATCH', route('api.tca.address.edit', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);
        PHPUnit::assertEquals('NEW Label', $response['result']['label']);
        PHPUnit::assertEquals(false, $response['result']['public']);
        PHPUnit::assertEquals(false, $response['result']['active']);

        $error_scenario_defaults = [
            'method'               => 'PATCH',
            'route'                => ['api.tca.address.edit', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']],
            'postVars'             => [],
            'expectedResponseCode' => 422,
            'expectedErrorString'  => 'null',
            'valid'                => false,
        ];
        $api_tester->testErrors([
            [
                'postVars'            => ['label' => str_repeat('x', 256)],
                'expectedErrorString' => 'may not be greater than 255 characters',
            ],
            [
                'postVars'            => ['public' => 'foobar'],
                'expectedErrorString' => 'must be true or false',
            ],
            [
                'postVars'            => ['active' => 'foobar'],
                'expectedErrorString' => 'must be true or false',
            ],
        ], $error_scenario_defaults);
    }


    public function testDeleteRegisteredAddress() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // add test users and addresses
        $user2 = $user_helper->createRandomUser();
        $address_helper->createNewAddress($user2, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);

        // add address
        $this->buildXChainMock();
        $vars = ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.new'), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);

        // check that it is there
        $address_model = Address::where('user_id', $user1->id)->where('address', '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j')->first();
        PHPUnit::assertNotEmpty($address_model);

        // delete address
        $response = $api_tester->expectAuthenticatedResponse('DELETE', route('api.tca.address.delete', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), $vars);
        PHPUnit::assertNotEmpty($response);

        // check that it is gone
        $address_model = Address::where('user_id', $user1->id)->where('address', '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j')->first();
        PHPUnit::assertEmpty($address_model);

        // cannot delete another address
        $response = $api_tester->expectAuthenticatedResponse('DELETE', route('api.tca.address.delete', ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']), $vars, 404);
    }


    public function testVerifyRegisteredAddress() {
        $user_helper = app('UserHelper');
        $address_helper = app('AddressHelper');

        // add test users and addresses
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // add address
        $this->buildXChainMock()->setBalances(['BTC' => 0.123]);
        $vars = ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.new'), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals('1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', $response['result']['address']);

        // verify validation
        $error_scenario_defaults = [
            'method'               => 'POST',
            'route'                => ['api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']],
            'postVars'             => ['signature' => 'foo'],
            'expectedResponseCode' => 422,
            'expectedErrorString'  => 'null',
            'valid'                => false,
        ];
        $api_tester->testErrors([
            [
                'postVars'            => ['signature' => str_repeat('x', 1256)],
                'expectedErrorString' => 'may not be greater than 1024 characters',
            ],
            [
                'postVars'            => ['signature' => ''],
                'expectedErrorString' => 'signature field is required',
            ],
        ], $error_scenario_defaults);

        // bad signature
        $vars = ['signature' => 'bad'];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), $vars, 400);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Invalid verification signature', $response['error']);

        // verify address
        $vars = ['signature' => 'foobar'];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertEquals(true, $response['result']);

        // verify again returns an error
        $vars = ['signature' => 'foobar'];
        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.address.verify', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']), $vars, 400);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('already verified', $response['error']);
    }

    // ------------------------------------------------------------------------

    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->mock_builder;
    }

}