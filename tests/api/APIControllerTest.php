<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* APIControllerTest
*/
class APIControllerTest extends TestCase {

    const SATOSHI = 100000000;

    protected $use_database = true;

    public function testCheckTokenAccessAPI() {
        $user_helper = $this->buildUserHelper();

        // create a user
        $user = $user_helper->createNewUser();

        // create a new address
        Address::unguard();
        $new_address = Address::create([
            'user_id'  => $user['id'],
            'type'     => 'BTC',
            'address'  => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD',
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);

        // add a new balance entry to the new address
        DB::Table('address_balances')->insert([
            'address_id' => $new_address->id,
            'asset'      => 'TOKENLY',
            'balance'    => 10 * self::SATOSHI,
            'updated_at' => time(),
        ]);
        DB::Table('address_balances')->insert([
            'address_id' => $new_address->id,
            'asset'      => 'LTBCOIN',
            'balance'    => 5000 * self::SATOSHI,
            'updated_at' => time(),
        ]);

        // create an oauth client
        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient();

        // add the scope
        $oauth_scope = app('Tokenpass\Repositories\OAuthScopeRepository')->create([
            'id'          => 'tca',
            'description' => 'TCA Access',
        ]);

        // create an oauth client connection
        $oauth_client_id = $oauth_client['id'];
        DB::table('client_connections')->insert([
            'uuid'       => '00000001',
            'user_id'    => $user['id'],
            'client_id'  => $oauth_client_id,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $oauth_connection = (array)DB::table('client_connections')->where('uuid', '00000001')->first();

        // create connection
        DB::table('client_connection_scopes')->insert([
            'connection_id' => $oauth_connection['id'],
            'scope_id'      => $oauth_scope['uuid'],
        ]);

        $route = route('api.tca.check', ['username' => $user['username']]);


        // check 10 TOKENLY (should be true)
        $token = 'TOKENLY';
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 10];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);

        // check 11 TOKENLY (should be false)
        $token = 'TOKENLY';
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 11];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);

        // check 10 TOKENLY AND 5000 LTBCOIN (should be true)
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 10, 'LTBCOIN' => 5000];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);

        // check 10 TOKENLY AND 5001 LTBCOIN (should be false)
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 10, 'LTBCOIN' => 5001];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);

        // check 10 TOKENLY OR 5001 LTBCOIN (should be true)
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 10, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);

        // check 11 TOKENLY OR 5001 LTBCOIN (should be false)
        $query_params = ['client_id' => $oauth_client_id, 'TOKENLY' => 11, 'LTBCOIN' => 5001, 'stackop_1' => 'OR'];
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);
    }

    public function testGetAddress() {

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
            'description' => 'manage-Address',
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

        $vars = [
            'client_id' => $oauth_client_id
        ];

        // Client ID is wrong
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', ['username' => 'FakeUsername']), ['client_id' => 'fake123'], 403);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // User is non existant
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', ['username' => 'FakeUsername']), $vars, 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Username not found', $response['error']);

        // User has no addresses
        $user_helper = $this->buildUserHelper();
        $user = $user_helper->createNewUser();
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', ['username' => $user->username ]), $vars);
        PHPUnit::assertEmpty($response['addresses']);

        // User has an address
        $address_helper = app('AddressHelper');
        $address_helper->createNewAddress($user, [
            'address' => '1sdBCPkJozaAqwLSomeAddress'
        ]);

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses', ['username' => $user->username ]), $vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('1sdBCPkJozaAqwLSomeAddress', $response['result'][0]['address']);
    }

    public function testGetAddressDetails() {

        $this->buildOAuthScope();

        // Invalid client ID
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses.details', ['username' => 'username', 'address' => '1NotRealAtAll']), ['client_id' => 'fake123'], 403);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Address does not exist
        $user_helper = $this->buildUserHelper();
        $user = $user_helper->createNewUser();
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses.details', ['username' => $user->username, 'address' => '1NotRealAtAll']), $this->vars, 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Address details not found', $response['error']);


        // User does not exist
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses.details', ['username' => 'FakeUser', 'address' => '1NotRealAtAll']), $this->vars, 404);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Username not found', $response['error']);

        // User has an address
        $address_helper = app('AddressHelper');
        $address = $address_helper->createNewAddress($user, [
            'address' => '1sdBCPkJozaAqwLSomeAddress'
        ]);
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.addresses.details', ['username' => $user->username, 'address' => $address->address]), $this->vars);
        PHPUnit::assertContains('btc', $response['result']['type']);
        PHPUnit::assertContains('1sdBCPkJozaAqwLSomeAddress', $response['result']['address']);
        PHPUnit::assertTrue($response['result']['verified']);
    }

    public function testRegisterAddress() {
        $this->buildOAuthScope();

        // Invalid API call
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.addresses.new'), ['client_id' => 'fake123'], 403);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Invalid OAuthToken call
        $this->vars['oauth_token'] = '1SomeToken';
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.addresses.new'), $this->vars, 403);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('Invalid user oauth token', $response['error']);

        // Missing bitcoin address
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $this->buildOAuthToken();
        $user_uuid = DB::table('users')->first();
        $this->vars['oauth_token'] = $this->vars['token'];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.addresses.new'), $this->vars, 400);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('btc address required', $response['error']);

        // Valid
        $this->buildXChainMock();
        $this->vars['address'] = '1NLwKTJVa5VMvaP62hNaPt3ddbpXLBE9Ug';
        $this->vars['type'] = 'btc';
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.addresses.new'), $this->vars);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('success', $response['result']);

    }
    
    public function testInstantVerifyAddressAPI()
    {
        // create a user
        $user_helper = $this->buildUserHelper();
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
    
    public function testProvisionalSourceAPI()
    {
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock = $mock_builder->installXChainMockClient($this);

        // create an oauth client
        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient();        

        $source_address = '1GGsaA2kBEUW1HRc5KvMnzEKpmHbQqzcmP';
        $source_address2 = '1157iDqgnkG87kyGSh1iF93grt1HQFVCHw';
        $source_address3 = '13tCQM6Nse3zugyYEJKZBuHAbr7irYx2Xp ';
        $proof = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        $proof_suffix = '_'.Provisional::getProofHash($oauth_client['id']);
        
        $default_params = array('client_id' => $oauth_client['id']);
        $query_params = $default_params;
        
        //register source address
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address;
        $query_params['proof'] = $proof;
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);        
        
        //register with asset restrictions
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address2;
        $query_params['assets'] = array('TOKENLY', 'LTBCOIN');
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);        
        
        //register with comma separated asset restrictions
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address3;
        $query_params['assets'] = 'TOKENLY, LTBCOIN';
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);          
        
        //delete a source address
        $route = route('api.tca.provisional.delete', $source_address);
        $query_params = $default_params;
        $request = Request::create($route, 'DELETE', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);      
        PHPUnit::assertTrue($json_data['result']);   
        
        //get list of source addresses
        $route = route('api.tca.provisional.list');
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);    
        PHPUnit::assertContains('whitelist', $json_data);    
        PHPUnit::assertEquals($proof_suffix, $json_data['proof_suffix']);    
        
        //make sure deletion really worked
        PHPUnit::assertThat($json_data['whitelist'], $this->logicalNot($this->arrayHasKey($source_address)));
        
    }
    
    public function testProvisionalTransactionAPI()
    {
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock = $mock_builder->installXChainMockClient($this);
        
        //register user
        $user_helper = $this->buildUserHelper();
        $user = $user_helper->createNewUser();

        // create an oauth client
        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient();    
        
        //setup some variables
        $source_address = '1BY44aSERnwUGNKBhTY8Zqp83FbjUXNxVS';
        $destination = '1GGsaA2kBEUW1HRc5KvMnzEKpmHbQqzcmP';
        $proof = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        $fingerprint = 'asdfghjklqwertyuiop';
        
        $default_params = array('client_id' => $oauth_client['id']);
        $query_params = $default_params;
        
        //add destination address to users TCA address list
        Address::unguard();
        $new_address = Address::create([
            'user_id'  => $user['id'],
            'type'     => 'BTC',
            'address'  => $destination,
            'label'    => 'Addr One',
            'verified' => 1,
            'public'   => 1,
        ]);

        // add a new balance entry to the new address
        DB::Table('address_balances')->insert([
            'address_id' => $new_address->id,
            'asset'      => 'SOUP',
            'balance'    => 1 * self::SATOSHI,
            'updated_at' => time(),
        ]);        
        
        //register source address to whitelist
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address;
        $query_params['proof'] = $proof;
        $query_params['assets'] = array('SOUP', 'LTBCOIN');
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);        
        
        //submit a provisional transaction / token promise with invalid asset
        $route = route('api.tca.provisional.tx.register');
        $query_params = $default_params;
        $query_params['source'] = $source_address;
        $query_params['destination'] = $destination;
        $query_params['asset'] = 'TOKENLY';
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);      
        
        //submit promise with valid asset, but value greater than balance
        $query_params['asset'] = 'SOUP';
        $query_params['quantity'] = intval(1250000000*self::SATOSHI);
        $query_params['expiration'] = time()+3600;
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);   
        
        //submit with invalid expiration
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $query_params['expiration'] = 100;
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);           
        
        //submit real promise 
        $query_params['expiration'] = time()+3600;
        $query_params['fingerprint'] = $fingerprint;
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);           
        PHPUnit::assertContains('tx', $json_data);
        $promise_id = $json_data['tx']['promise_id'];
        
        //get provisional tx/promise
        $query_params = $default_params;
        $route = route('api.tca.provisional.tx.get', $fingerprint);
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);           
        PHPUnit::assertContains('tx', $json_data);
        
        //register another one
        $route = route('api.tca.provisional.tx.register');
        $query_params = $default_params;
        $query_params['source'] = $source_address;
        $query_params['destination'] = $destination;        
        $query_params['asset'] = 'SOUP';        
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $query_params['expiration'] = time()+3600;
        $query_params['ref'] = 'test ref data';
        $request = Request::create($route, 'POST', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);           
        PHPUnit::assertContains('tx', $json_data);
        $promise_id2 = $json_data['tx']['promise_id'];  
        
        //update provisional tx with invalid amount
        $route = route('api.tca.provisional.tx.update', $promise_id);
        $query_params = $default_params;
        $query_params['quantity'] = intval(12500000*self::SATOSHI);
        $request = Request::create($route, 'PATCH', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);
        
        //update provisional tx with invalid expiration
        $query_params['quantity'] = intval(2000*self::SATOSHI);
        $query_params['expiration'] = 100;
        $request = Request::create($route, 'PATCH', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true); 
        PHPUnit::assertFalse($json_data['result']);           
        
        //update provisional tx for real
        $query_params['expiration'] = time()+86400;
        $query_params['txid'] = '1091247b29e452673851411c2df733ba10ed872c57540726821c26d1afb39fc9';
        $query_params['ref'] = 'testing update';
        $request = Request::create($route, 'PATCH', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true); 
        PHPUnit::assertTrue($json_data['result']);       
        PHPUnit::assertContains('tx', $json_data);
        PHPUnit::assertEquals($query_params['ref'], $json_data['tx']['ref']);
        
        //delete second provisional tx
        $route = route('api.tca.provisional.tx.delete', $promise_id2);
        $query_params = $default_params;
        $request = Request::create($route, 'DELETE', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);         
        PHPUnit::assertTrue($json_data['result']);
        
        //confirm deletion
        $route = route('api.tca.provisional.tx.get', $promise_id2);
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertFalse($json_data['result']);                 
        
        //get list of promised transactions
        $route = route('api.tca.provisional.tx.list');
        $request = Request::create($route, 'GET', $query_params, []);
        $response = app('Illuminate\Contracts\Http\Kernel')->handle($request);
        $json_data = json_decode($response->getContent(), true);
        PHPUnit::assertTrue($json_data['result']);           
        PHPUnit::assertContains('list', $json_data);           
        
        //make sure provisional balance is applied to user
        $balances = Address::getAllUserBalances($user['id']);
        PHPUnit::assertArrayHasKey('SOUP', $balances);
        PHPUnit::assertEquals((2000+1)*self::SATOSHI, $balances['SOUP']);
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

    public function testUpdateAccount() {

        // Missing Client_ID
        $missing_client_id = [
            'user_id' => 'Tester',
            'current_password' => 'abc123456',
            'email'   => 'test@tokenly.com',
            'token'   => '1Token'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_client_id);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Missing Username
        $this->buildOAuthScope();

        $missing_user = [
            'current_password' => 'abc123456',
            'email'   => 'test@tokenly.com',
            'client_id' =>  'MY_API_TOKEN',
            'token'   => '1Token'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_user);
        PHPUnit::assertContains('User ID required', $response['error']);

        // Missing Password

        $missing_pass = [
            'user_id' => 'Tekj4b3t4otboto34ster',
            'token'   => '1TokenSomething',
            'client_id' =>  'MY_API_TOKEN'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_pass);
        PHPUnit::assertContains('Current password required', $response['error']);

        // Wrong Token
        $missing_email = [
            'user_id' => '1',
            'current_password' => 'abc123456',
            'client_id' =>  'MY_API_TOKEN',
            'token'   => '1Token'
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $missing_email);
        PHPUnit::assertContains('Invalid access token, client ID or user ID', $response['error']);


        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $address_helper->createNewAddress($user);
        $this->buildOAuthToken();
        $user_uuid = DB::table('users')->first();


        // Real result
        $vars = [
            'user_id' => $user_uuid->uuid,
            'current_password' => 'abc123456',
            'client_id' =>  'MY_API_TOKEN',
            'email'   => 'test@tokenly.com',
            'token'   => $this->vars['token']
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $vars);
        PHPUnit::assertContains('success', $response['result']);

        // Wrong password

        $vars = [
            'user_id' => $user_uuid->uuid,
            'current_password' => 'Nefarious_logger',
            'client_id' =>  'MY_API_TOKEN',
            'email'   => 'test@tokenly.com',
            'token'   => $this->vars['token']
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('PATCH', route('api.update-account'), $vars);
        PHPUnit::assertContains('Invalid password', $response['error']);
    }

    public function testLookupUserByAddress() {
        $this->buildOAuthScope();

        // create a new user and address
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $address_helper->createNewAddress($user);

        // No Client ID
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']),[],403);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Real details
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD', 'client_id' => 'MY_API_TOKEN']),[],200);
        PHPUnit::assertContains('johndoe', $response['result']['username']);

        // Multiple Details
        $alt_user = $user_helper->createAltUser();
         $address_helper->createNewAddress($alt_user, [
            'user_id' => '2',
            'address' => '1sdBCPkJozaAqwLF3mTEgNS8Uu95NMVdp'
        ]);

        // Not Compatible with Arrays

//        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.address', [
//            'address_list' => [
//                0 =>'1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD',
//                1 =>'1sdBCPkJozaAqwLF3mTEgNS8Uu95NMVdp'],
//            'client_id' => 'MY_API_TOKEN']),[],200);

    }

    public function testLookupAddressByUser() {
        $this->buildOAuthScope();

        // create a new user and address
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $address_helper->createNewAddress($user);

        // No Client ID
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', ['user' => 'johndoe', 'client_id' => 'fake']),[],403);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // No user
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', ['user' => 'fake dude', 'client_id' => 'MY_API_TOKEN']),[],404);
        PHPUnit::assertContains('User not found', $response['error']);

        // Real Details
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.user', ['user' => 'johndoe', 'client_id' => 'MY_API_TOKEN']),[],200);
        PHPUnit::assertInternalType('string', $response['result']['username']);
        PHPUnit::assertInternalType('string', $response['result']['address']);
        PHPUnit::assertInternalType('string', $response['result']['email']);
    }

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
        $user_helper = $this->buildUserHelper();
        $user = $user_helper->createNewUser();


    }

    public function testCheckSignRequirement() {
        $this->buildOAuthScope();
        $user_helper = app('UserHelper')->setTestCase($this);

        $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);

        // Bad client ID
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.check-sign', ['username' => 'johndoe', 'client_id' => 'fakeID']),[],403);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        // Non existent user
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.check-sign', ['username' => 'fakename', 'client_id' => 'MY_API_TOKEN']),[],404);
        PHPUnit::assertContains('Username not found', $response['error']);

        // Real result
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.tca.check-sign', ['username' => 'johndoe', 'client_id' => 'MY_API_TOKEN']),[],200);
        PHPUnit::assertContains('unsigned', $response['result']);
    }

    public function testSetSignRequirement() {
        $this->buildOAuthScope();

        $vars = [
          'username' => 'johndoe'
        ];

        // Bad client ID
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.set-sign'), $vars, 403);
        PHPUnit::assertContains('Invalid API client ID', $response['error']);

        $vars = [
            'username' => 'johndoe',
            'client_id' => 'MY_API_TOKEN'
        ];

        // Non existent user
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.set-sign'), $vars, 404);
        PHPUnit::assertContains('Username not found', $response['error']);

        // Valid Signature
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $this->buildOAuthToken();
        $user = $user_helper->createNewUser();
        $address_helper->createNewAddress($user, ['address' =>'1sdBCPkJozaAqwLF3mTEgNS8Uu95NMVdp']);
        $user_uuid = DB::table('users')->first();
        $this->forceUserCryptographicData($user);
        $this->buildXChainMock();

        $user_meta = DB::table('user_meta')->get();

        $vars = [
            'username' => 'johndoe',
            'client_id' => 'MY_API_TOKEN',
            'user_id'   => $user_uuid->uuid,
            'token' => $this->vars['token'],
            'signature' => 'IM46C3aqnn6vVeV1RtTfS+HbBbHehOt/yOrzyRKqTJRNegZRrjm1cxFlZLUfCHSO5HNJL7gDXFPB/+r4atxSkJQ='
        ];

        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.tca.set-sign'), $vars);
        PHPUnit::assertContains('Signed', $response['result']);

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
        DB::table('oauth_access_tokens')->insert([
            'id' => 'TFR1QrIFQTdaLqlr',
            'session_id' => '1',
            'expire_time' => time() + 50000
        ]);

        DB::table('oauth_sessions')->insert([
            'client_id' => 'MY_API_TOKEN',
            'owner_type' => 'user',
            'owner_id' => '1',
            'client_redirect_uri' => 'http://fake.url'
        ]);

        $this->vars['token'] = 'TFR1QrIFQTdaLqlr';
    }

    protected function forceUserCryptographicData($user) {

       $result = Address::getUserVerificationCode($user);
       $instantCode = Address::getInstantVerifyMessage($user);

        DB::table('user_meta')->update([
            'meta_value' => '1',
            'updated_at' => time() + 50000
        ]);
        
        
    }

    protected function buildUserHelper() {
        $user_helper = app('UserHelper')->setTestCase($this);
        return $user_helper;
    }

    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
    }
}
