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
        $address = $address_helper->createNewAddress($user1, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v', 'public'        => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA4444xxxxxxxxxxxxxxxxxxxxjbqeD', 'active_toggle' => false,]);
        $address_helper->createNewAddress($user1, ['address' => '1AAAA5555xxxxxxxxxxxxxxxxxxxwEhYkL', 'verified'      => false,]);

        //New provisional tca Address
        $proof = 'IHnyXpEMX+Dhu/em3SYEC+pLZPQYI1EblsjIGpPEVy2SmPJ1p6CBDvy71llh6lYMt5SxTx51SOImSpIp1PQoGUI=';
        \Tokenpass\Models\ProvisionalWhitelist::create(array('address' => $address->address, 'client_id' => $oauth_client->id,
                                  'assets' => json_encode(array('TOKENLY', 'LTBCOIN')), 'proof' => $proof ));


        //TODO: Make this a method
        //Mock Xchain
        $mock_t = \Mockery::mock('Tokenly\XChainClient\Client');
        $mock_t->shouldReceive('getBalances')->andReturn(array('TOKENLY' => INF));
        app()->instance('Tokenly\XChainClient\Client', $mock_t);


        // No user
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.tca.provisional.tx.register', [
            'source' => $address->address,
            'destination' => 'email:fakemmail@tokenly.com',
            'asset' => 'TOKENLY',
            'quantity' => 15
        ]), [], 200);

    }

}
