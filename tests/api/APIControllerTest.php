<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* APIControllerTest
*/
class APIControllerTest extends TestCase {


    protected $use_database = true;




    
    public function testInstantVerifyAddressAPI()
    {
        // create a user
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $alt_user = $user_helper->createAltUser();
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock = $mock_builder->installXChainMockClient($this);
        
        $user->uuid = '1234567890'; 
        $user->save(); //set a predictable uuid so we can test with a premade signature

        $alt_user->uuid = '1234';
        $alt_user->save();

        // Private key used is : KzPMHLZfubuRR8GxZyG2vygqWk391RuEGTqFH1jUtyWgKXrH3FFT
        $new_address = '1sdBCPkJozaAqwLF3mTEgNS8Uu95NMVdp';
        $address_sig = 'IM46C3aqnn6vVeV1RtTfS+HbBbHehOt/yOrzyRKqTJRNegZRrjm1cxFlZLUfCHSO5HNJL7gDXFPB/+r4atxSkJQ=';
        $alt_address_sig = 'Hzk9Inq3too7fJqiZKFcWbD/YhaYzl6e2LmoSYCLldYsPwYDiZTlZJaK/3izovOzd8/wissGMigqG36LB19k9nM=';
        $sig_message = 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646';
        $alt_sig_message = '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4';

        //test with a bogus user
        $route = route('api.instant-verify', 123123);
        $query_params = ['msg' => $sig_message, 'sig' => $address_sig, 'address' => $new_address];
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);
        
        //test with no address
        $query_params = ['msg' => $sig_message, 'sig' => $address_sig];
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);
        
        //test with missing input
        $query_params = ['sig' => $address_sig, 'address' => $new_address];
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);        

        //test with wrong message
        $route = route('api.instant-verify', $user->username);
        $query_params = ['msg' => 'qwerty', 'sig' => $address_sig, 'address' => $new_address];
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);

        //test with all correct info
        $address_helper = app('AddressHelper');
        $address_helper->createNewAddress($user, ['address' =>'1Z5bsDeHrtCr2K8xmWFjb8kfzT7hgTrqa']);
        $signature = 'H9jrg5kSpW8Wffbp1ZIAWu1uytjN156DHcvTGIktgA9RfhFk8u39OKz0JV8cXltOZeh7cJ5H7eoFN+YWSxjWqmw=';
        $this->forceUserCryptographicData($user);

        $route = route('api.instant-verify', $user->username); //set proper route
        $query_params = ['msg' => '1', 'sig' => $signature, 'address' => '1sdBCPkJozaAqwLF3mTEgNS8Uu95NMVdp'];
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);
        
    }
    
    public function testRegisterAccount() {

        // Missing Client_ID
        $missing_client_id = [
            'username' => 'Tester',
            'password' => 'abc123456',
            'email'   => 'test@tokenly.com',
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.register'), $missing_client_id);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Missing Username
        $missing_user = [
            'password' => 'abc123456',
            'email'   => 'test@tokenly.com',
            'client_id' =>  '1234'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.register'), $missing_user);
        PHPUnit::assertContains('Username required', $response['error']);

        // Missing Password
        $missing_pass = [
            'username' => 'Tester',
            'email'   => 'test@tokenly.com',
            'client_id' =>  '1234'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.register'), $missing_pass);
        PHPUnit::assertContains('Password required', $response['error']);

        // Missing Email
        $missing_email = [
            'username' => 'Tester',
            'password' => 'abc123456',
            'client_id' =>  '1234'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.register'), $missing_email);
        PHPUnit::assertContains('Email required', $response['error']);

        // Register details
        $this->buildOAuthScope();

        $vars = [
            'username' => 'Tester',
            'password' => 'abc123456',
            'email'   => 'test@tokenly.com',
            'client_id' =>  'MY_API_TOKEN'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.register'), $vars);
        PHPUnit::assertNotEmpty($response);
;       PHPUnit::assertInternalType('string', $response['result']['id']);
    }

    // public function testUpdateAccount() {

    //     // Missing Client_ID
    //     $missing_client_id = [
    //         'user_id' => 'Tester',
    //         'current_password' => 'abc123456',
    //         'email'   => 'test@tokenly.com',
    //         'token'   => '1Token'
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_client_id);
    //     PHPUnit::assertContains('Invalid API client ID', $response['error']);

    //     // Missing Username
    //     $this->buildOAuthScope();

    //     $missing_user = [
    //         'current_password' => 'abc123456',
    //         'email'   => 'test@tokenly.com',
    //         'client_id' =>  'MY_API_TOKEN',
    //         'token'   => '1Token'
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_user);
    //     PHPUnit::assertContains('User ID required', $response['error']);

    //     // Missing Password

    //     $missing_pass = [
    //         'user_id' => 'Tekj4b3t4otboto34ster',
    //         'token'   => '1TokenSomething',
    //         'client_id' =>  'MY_API_TOKEN'
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_pass);
    //     PHPUnit::assertContains('Current password required', $response['error']);

    //     // Wrong Token
    //     $missing_email = [
    //         'user_id' => '1',
    //         'current_password' => 'abc123456',
    //         'client_id' =>  'MY_API_TOKEN',
    //         'token'   => '1Token'
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_email);
    //     PHPUnit::assertContains('Invalid access token, client ID or user ID', $response['error']);


    //     $address_helper = app('AddressHelper');
    //     $user_helper = app('UserHelper')->setTestCase($this);
    //     $user = $user_helper->createNewUser();
    //     $address_helper->createNewAddress($user);
    //     $this->buildOAuthToken();
    //     $user_uuid = DB::table('users')->first();


    //     // Real result
    //     $vars = [
    //         'user_id' => $user_uuid->uuid,
    //         'current_password' => 'abc123456',
    //         'client_id' =>  'MY_API_TOKEN',
    //         'email'   => 'test@tokenly.com',
    //         'token'   => $this->vars['token']
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $vars);
    //     PHPUnit::assertContains('success', $response['result']);

    //     // Wrong password

    //     $vars = [
    //         'user_id' => $user_uuid->uuid,
    //         'current_password' => 'Nefarious_logger',
    //         'client_id' =>  'MY_API_TOKEN',
    //         'email'   => 'test@tokenly.com',
    //         'token'   => $this->vars['token']
    //     ];

    //     $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $vars);
    //     PHPUnit::assertContains('Invalid password', $response['error']);
    // }


    public function testRequestOAuth() {
        $this->buildOAuthScope();

        $vars = [
            'state' => 'Tekj4b3t4otboto34ster',
            'client_id' =>  'wrong'
        ];

        // $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.oauth.request'),$vars, 400);

        $vars = [
            'state' => 'Tekj4b3t4otboto34ster',
            'client_id' =>  'MY_API_TOKEN'
        ];
        // $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.oauth.request'),$vars);
    }

    public function testGetOAuthToken() {

        // Placement holder
    }

    public function testCheckAddressTokenAccess()
    {
        // mock
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock_builder->installXChainMockClient($this);

        //register user
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();


    }

    public function testCheckSignRequirement() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $address_helper->createNewAddress($user1, ['address' =>'1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // require auth
        app('OauthUserAPITester')->expectUnauthenticatedResponse('GET', route('api.tca.check-sign', ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']));

        // Non existent user
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.check-sign', ['username' => 'fakename']),[],404);
        PHPUnit::assertContains('Username not found', $response['error']);

        // Real result
        $response = $api_tester->expectAuthenticatedResponse('GET', route('api.tca.check-sign', ['username' => $user1['username']]),[],200);
        PHPUnit::assertContains('unsigned', $response['result']);
    }

    public function testSetSignRequirement() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');
        list($user1, $user1_token, $oauth_client) = $user_helper->createRandomUserWithOAuthSession();
        $address_helper->createNewAddress($user1, ['address' =>'1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $api_tester = app('OauthUserAPITester')->setToken($user1_token);

        // require auth
        app('OauthUserAPITester')->expectUnauthenticatedResponse('POST', route('api.tca.set-sign'));

        $vars = [
            'username' => $user1['username'],
        ];

        // Valid Signature
        $this->forceUserCryptographicData($user1);
        $this->buildXChainMock();

        $user_meta = DB::table('user_meta')->get();

        $vars = [
            'username'  => $user1['username'],
            'user_id'   => $user1['uuid'],
            'signature' => 'IM46C3aqnn6vVeV1RtTfS+HbBbHehOt/yOrzyRKqTJRNegZRrjm1cxFlZLUfCHSO5HNJL7gDXFPB/+r4atxSkJQ='
        ];

        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.set-sign'), $vars);
        PHPUnit::assertEquals('Signed', $response['result']);

        $response = $api_tester->expectAuthenticatedResponse('POST', route('api.tca.set-sign'), array_merge($vars,['signature' => '']), 422);
        PHPUnit::assertContains('signature field is required', $response['errors'][0]);

    }

    ////////////////////////////////////////////////////////////////////////

    protected function buildOAuthScope() {
        // create an oauth client
        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient();
        $oauth_scope_tca = app('Tokenpass\Repositories\OAuthScopeRepository')->create([
            'id'          => 'tca',
            'description' => 'TCA Access',
        ]);
        $oauth_scope_pa = app('Tokenpass\Repositories\OAuthScopeRepository')->create([
            'id'          => 'private-address',
            'description' => 'Private-Address',
        ]);
        $oauth_scope_ma = app('Tokenpass\Repositories\OAuthScopeRepository')->create([
            'id'          => 'manage-address',
            'description' => 'Manage Addresses',
        ]);        

        $oauth_client_id = $oauth_client['id'];
        DB::table('client_connections')->insert([
            'uuid'       => '00000001',
            'user_id'    => 1,
            'client_id'  => $oauth_client_id,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $oauth_connection = (array)DB::table('client_connections')->where('uuid', '00000001')->first();
        DB::table('client_connection_scopes')->insert([
            'connection_id' => $oauth_connection['id'],
            'scope_id'      => $oauth_scope_tca['uuid'],
        ]);
        DB::table('client_connection_scopes')->insert([
            'connection_id' => $oauth_connection['id'],
            'scope_id'      => $oauth_scope_pa['uuid'],
        ]);
        DB::table('client_connection_scopes')->insert([
            'connection_id' => $oauth_connection['id'],
            'scope_id'      => $oauth_scope_ma['uuid'],
        ]);        

        $this->vars = [
            'client_id' => $oauth_client_id
        ];
    }

    protected function buildOAuthToken() {
        $token = 'TFR1QrIFQTdaLqlr';

        DB::table('oauth_access_tokens')->insert([
            'id' => $token,
            'session_id' => '1',
            'expire_time' => time() + 50000
        ]);

        DB::table('oauth_sessions')->insert([
            'client_id' => 'MY_API_TOKEN',
            'owner_type' => 'user',
            'owner_id' => '1',
            'client_redirect_uri' => 'http://fake.url'
        ]);

        $this->vars['token'] = $token;

        return $token;
    }

    protected function forceUserCryptographicData($user) {

       $result = Address::getUserVerificationCode($user);
       $instantCode = Address::getInstantVerifyMessage($user);

        DB::table('user_meta')->update([
            'meta_value' => '1',
            'updated_at' => time() + 50000
        ]);
        
        
    }

    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->mock_builder;
    }
}
