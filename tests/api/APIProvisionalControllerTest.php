<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\Provisional;

/*
* APIProvisionalControllerTest
*/
class APIProvisionalControllerTest extends TestCase {

    const SATOSHI = 100000000;

    protected $use_database = true;

    public function testRequireAuthenticationForProvisionalAPIMethods() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);


        $response = $api_tester->testRequireAuth('GET', route('api.tca.provisional.list'));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('POST', route('api.tca.provisional.register'));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('DELETE', route('api.tca.provisional.delete', '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j'));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('GET', route('api.tca.provisional.tx.list'));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('POST', route('api.tca.provisional.tx.register'));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('GET', route('api.tca.provisional.tx.get', ['id' => 'foo']));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('PATCH', route('api.tca.provisional.tx.update', ['id' => 'foo']));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);

        $response = $api_tester->testRequireAuth('DELETE', route('api.tca.provisional.tx.delete', ['id' => 'foo']));
        PHPUnit::assertContains('Missing authentication credentials', $response['message']);
    }

    public function testProvisionalSourceAPI() {
        // install xchain mock
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock = $mock_builder->installXChainMockClient($this);

        // create an api client
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // create a second api client
        $user2 = $user_helper->createRandomUser();
        $oauth_client2 = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user2);
        $api_tester2 = app('OAuthClientAPITester')->be($oauth_client2);

        $source_address = '1GGsaA2kBEUW1HRc5KvMnzEKpmHbQqzcmP';
        $source_address2 = '1157iDqgnkG87kyGSh1iF93grt1HQFVCHw';
        $source_address3 = '13tCQM6Nse3zugyYEJKZBuHAbr7irYx2Xp ';
        $proof = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        $proof_suffix = '_'.Provisional::getProofHash($oauth_client['id']);
        
        $default_params = array();
        $query_params = $default_params;

        // require authentication
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address;
        $query_params['proof'] = $proof;
        
        //register source address
        $query_params['address'] = $source_address;
        $query_params['proof'] = $proof;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);        

        //register with asset restrictions
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address2;
        $query_params['assets'] = ['TOKENLY', 'LTBCOIN'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);
        $results = app('Tokenpass\Models\ProvisionalWhitelist')->where('client_id', $oauth_client['id'])->where('address', $source_address2)->get();
        PHPUnit::assertCount(1, $results);
        PHPUnit::assertEquals($source_address2, $results[0]['address']);
        PHPUnit::assertEquals(['TOKENLY', 'LTBCOIN'], json_decode($results[0]['assets'], 1));
        
        //register with comma separated asset restrictions
        $route = route('api.tca.provisional.register');
        $query_params['address'] = $source_address3;
        $query_params['assets'] = 'TOKENLY, LTBCOIN';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);          
        $results = app('Tokenpass\Models\ProvisionalWhitelist')->where('client_id', $oauth_client['id'])->where('address', $source_address3)->get();
        PHPUnit::assertCount(1, $results);
        PHPUnit::assertEquals($source_address3, $results[0]['address']);
        PHPUnit::assertEquals(['TOKENLY', 'LTBCOIN'], json_decode($results[0]['assets'], 1));

        // try to get a list from another client (should return nothing)
        $route = route('api.tca.provisional.list');
        $response = $api_tester2->callAPIWithAuthenticationAndReturnJSONContent('GET', $route);
        PHPUnit::assertTrue($response['result']);    
        PHPUnit::assertCount(0, $response['whitelist']);    

        // delete a source address
        $route = route('api.tca.provisional.delete', $source_address);
        $query_params = $default_params;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('DELETE', $route, $query_params);
        PHPUnit::assertTrue($response['result']);   
        
        // get list of source addresses
        $route = route('api.tca.provisional.list');
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);    
        PHPUnit::assertContains('whitelist', $response);    
        PHPUnit::assertEquals($proof_suffix, $response['proof_suffix']);

        // make sure deletion really worked
        PHPUnit::assertArrayNotHasKey($source_address, $response['whitelist']);
    }

    public function testProvisionalTransactionAPI() {
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $mock_builder->setBalances(['BTC' => 0.123]);
        $mock = $mock_builder->installXChainMockClient($this);
        
        //register user
        // create an oauth client
        $user = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);
        
        //setup some variables
        $source_address = '1BY44aSERnwUGNKBhTY8Zqp83FbjUXNxVS';
        $destination    = '1GGsaA2kBEUW1HRc5KvMnzEKpmHbQqzcmP';
        $proof          = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        $fingerprint    = 'asdfghjklqwertyuiop';
        
        $default_params = [];
        $query_params = $default_params;
        
        //add destination address to users TCA address list
        $new_address = $address_helper->createNewAddress($user, ['address' => $destination]);

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
        $query_params['assets'] = ['SOUP', 'LTBCOIN'];
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);        
        
        //submit a provisional transaction / token promise with invalid asset
        $route = route('api.tca.provisional.tx.register');
        $query_params = $default_params;
        $query_params['source'] = $source_address;
        $query_params['destination'] = $destination;
        $query_params['asset'] = 'TOKENLY';
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params, 400);
        PHPUnit::assertFalse($response['result']);      
        PHPUnit::assertContains('Asset not allowed', $response['error']);

        //submit promise with valid asset, but value greater than balance
        $query_params['asset'] = 'SOUP';
        $query_params['quantity'] = intval(1250000000*self::SATOSHI);
        $query_params['expiration'] = time()+3600;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params, 400);
        PHPUnit::assertFalse($response['result']);      
        PHPUnit::assertContains('insufficient asset balance', $response['error']);

        //submit with invalid expiration
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $query_params['expiration'] = 100;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params, 400);
        PHPUnit::assertFalse($response['result']);      
        PHPUnit::assertContains('Invalid expiration', $response['error']);
        
        //submit real promise 
        $query_params['expiration'] = time()+3600;
        $query_params['fingerprint'] = $fingerprint;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);           
        PHPUnit::assertContains('tx', $response);
        $promise_id = $response['tx']['promise_id'];

        //get provisional tx/promise
        $query_params = $default_params;
        $route = route('api.tca.provisional.tx.get', $fingerprint);
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);           
        PHPUnit::assertContains('tx', $response);
        
        //register another one
        $route = route('api.tca.provisional.tx.register');
        $query_params = $default_params;
        $query_params['source'] = $source_address;
        $query_params['destination'] = $destination;        
        $query_params['asset'] = 'SOUP';        
        $query_params['quantity'] = intval(1250*self::SATOSHI);
        $query_params['expiration'] = time()+3600;
        $query_params['ref'] = 'test ref data';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', $route, $query_params);
        PHPUnit::assertTrue($response['result']);           
        PHPUnit::assertContains('tx', $response);
        $promise_id2 = $response['tx']['promise_id'];  

        //update provisional tx with invalid amount
        $route = route('api.tca.provisional.tx.update', $promise_id);
        $query_params = $default_params;
        $query_params['quantity'] = intval(12500000*self::SATOSHI);
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('PATCH', $route, $query_params, 400);
        PHPUnit::assertFalse($response['result']);
        PHPUnit::assertContains('insufficient asset balance', $response['error']);

        //update provisional tx with invalid expiration
        $query_params['quantity'] = intval(2000*self::SATOSHI);
        $query_params['expiration'] = 100;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('PATCH', $route, $query_params, 400);
        PHPUnit::assertFalse($response['result']);           
        PHPUnit::assertContains('must be sometime in the future', $response['error']);
        
        //update provisional tx for real
        $query_params['expiration'] = time()+86400;
        $query_params['txid'] = '1091247b29e452673851411c2df733ba10ed872c57540726821c26d1afb39fc9';
        $query_params['ref'] = 'testing update';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('PATCH', $route, $query_params);
        PHPUnit::assertTrue($response['result']);       
        PHPUnit::assertContains('tx', $response);
        PHPUnit::assertEquals($query_params['ref'], $response['tx']['ref']);

        //delete second provisional tx
        $route = route('api.tca.provisional.tx.delete', $promise_id2);
        $query_params = $default_params;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('DELETE', $route, $query_params);
        PHPUnit::assertTrue($response['result']);

        //confirm deletion
        $route = route('api.tca.provisional.tx.get', $promise_id2);
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params, 404);
        PHPUnit::assertFalse($response['result']);                 
        
        //get list of promised transactions
        $route = route('api.tca.provisional.tx.list');
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', $route, $query_params);
        PHPUnit::assertTrue($response['result']);           
        PHPUnit::assertContains('list', $response);
        
        //make sure provisional balance is applied to user
        $balances = Address::getAllUserBalances($user['id']);
        PHPUnit::assertArrayHasKey('SOUP', $balances);
        PHPUnit::assertEquals((2000+1)*self::SATOSHI, $balances['SOUP']);
    }

    // ------------------------------------------------------------------------
    
    protected function buildXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->mock_builder;
    }

}
