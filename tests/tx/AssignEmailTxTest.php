<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\Address;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

class AssignEmailTxTest extends TestCase {

    protected $use_database = true;


    public function testEmailTxAssignment() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $address_helper = app('AddressHelper');

        $user1 = $user_helper->createRandomUser();

        // setup api client
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);

        // add test users and addresses
        $address = $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j', 'verified' => true, 'public' => true, 'active_toggle' => true]);

        //New provisional tca Address
        $proof = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        \Tokenpass\Models\ProvisionalWhitelist::create(array('address' => $address->address, 'client_id' => $oauth_client->id,
                                  'assets' => json_encode(array('TOKENLY', 'LTBCOIN')), 'proof' => $proof ));


        //TODO: Make this a method
        //Mock Xchain
        $mock_t = \Mockery::mock('Tokenly\XChainClient\Client');
        $mock_t->shouldReceive('getBalances')->andReturn(array('TOKENLY' => INF));
        app()->instance('Tokenly\XChainClient\Client', $mock_t);

        //Mock TokenDelivery client
        $mock_t = \Mockery::mock('\Tokenpass\TokenDelivery\DeliveryClient');
        $mock_t->shouldReceive('updateEmailTx')->andReturn(true);
        app()->instance('\Tokenpass\TokenDelivery\DeliveryClient', $mock_t);


        $query_params['source'] = $address->address;
        $query_params['destination'] = 'email:fakemmail@tokenly.com';
        $query_params['asset'] = 'TOKENLY';
        $query_params['quantity'] = 1250;
        $query_params['expiration'] = date('Y-m-d H:i:s', time()+86400);
        $query_params['ref'] = 'test ref data';
        $query_params['debug'] = true;
 
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.tca.provisional.tx.register', $query_params), [], 200);

        //Now assign the tx to a real user
        $user_vars = array('email' => 'fakemmail@tokenly.com');
        $new_user = $user_helper->registerNewUser($this->app, $user_vars);


        //Test that destination changed
        $tx = Provisional::orderBy('id', 'desc')->first();
        PHPUnit::assertEquals('user:'.$new_user->id, $tx->destination);

    }

}
