<?php

use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\AppCredits;
use Tokenpass\Models\AppCreditAccount;
use Tokenpass\Models\AppCreditTransaction;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* APIAppCreditsTest
*/
class APIAppCreditsTest extends TestCase {

    protected $use_database = true;
    
    
    public function testCreateAppCreditGroup()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //setup new credit group info
        $name = 'Store Credits';
        $app_whitelist = $oauth_client->id;
        
        $params = array();
        $params['name'] = $name;
        $params['app_whitelist'] = $app_whitelist;
        
        //try with no name set
        $params['name'] = null;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.new'), $params, 400);
        
        //try with invalid app_whitelist
        $params['name'] = $name;
        $params['app_whitelist'] = 'asdf';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.new'), $params, 400);

        //try for real
        $params['app_whitelist'] = $app_whitelist;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.new'), $params);
        PHPUnit::assertArrayHasKey('credit_group', $response);
        PHPUnit::assertEquals($name, $response['credit_group']['name']);
        
        //get details
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.details', $response['credit_group']['uuid']));
        PHPUnit::assertEquals($name, $response['credit_group']['name']);
        
        //test credit group listings
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.list'));
        PHPUnit::assertGreaterThan(0, count($response['list']));
    }
    
    public function testUpdateAppCreditGroup()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $user2 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $oauth_client2 = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user2);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //setup new credit group info
        $name = 'Store Credits';
        $app_whitelist = $oauth_client->id;
        
        $params = array();
        $params['name'] = $name;
        $params['app_whitelist'] = $app_whitelist;
        
        //create credit group
        $params['app_whitelist'] = $app_whitelist;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.new'), $params);
        PHPUnit::assertArrayHasKey('credit_group', $response);
        PHPUnit::assertEquals($name, $response['credit_group']['name']);  
        
        $credit_group = $response['credit_group'];  
        
        //now update...    
        
        $new_name = 'Streaming Credits';
        $new_app_whitelist = $oauth_client->id."\n".$oauth_client2->id;
        
        $params['name'] = $new_name;
        $params['app_whitelist'] = $new_app_whitelist;
        
        //test invalid whitelist
        $params['app_whitelist'] = 'asdf';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('PATCH', route('api.credits.update', $credit_group['uuid']), $params, 400);
        
        //test actual update
        $params['app_whitelist'] = $new_app_whitelist;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('PATCH', route('api.credits.update', $credit_group['uuid']), $params);
        PHPUnit::assertArrayHasKey('credit_group', $response);
        PHPUnit::assertEquals($new_name, $response['credit_group']['name']);        
        
    }
    
    public function testDebitAndCreditCreditAccounts()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //create sample app credit group
        $credit_group = $credits_helper->defaultAppCreditGroup($user1->id);
        $account2 = $credit_group->newAccount('Master funds');
        
        //create an account via API
        $params = array();
        $params['name'] = 'Test account 123';
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.accounts.new', $credit_group->uuid), $params);
        PHPUnit::assertEquals($params['name'], $response['account']['name']);
        
        $account = $response['account'];
        
        //credit account
        $credit_list = array(array('account' => $account['uuid'], 'amount' => 5000),
                             array('account' => $account['uuid'], 'amount' => 1000, 'source' => $account2->uuid));
        $credit_params = array();
        $credit_params['accounts'] = $credit_list;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.accounts.credit', $credit_group->uuid), $credit_params);
        PHPUnit::assertArrayHasKey('transactions', $response);
        
        //debit account
        $debit_list = array(array('account' => $account['uuid'], 'amount' => 1500, 'destination' => $account2->uuid));
        $debit_params = array();
        $debit_params['accounts'] = $debit_list;
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('POST', route('api.credits.accounts.debit', $credit_group->uuid), $debit_params);
        PHPUnit::assertArrayHasKey('transactions', $response);        
        
        //load account balance & verify
        $get_account = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.accounts.details', array($credit_group->uuid, $account['uuid'])));
        $get_account = $get_account['account'];
        PHPUnit::assertEquals(4500, $get_account['balance']);
        
        $get_account2 = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.accounts.details', array($credit_group->uuid, $account2->uuid)));
        $get_account2 = $get_account2['account'];
        PHPUnit::assertEquals(500, $get_account2['balance']);

    }
    
    public function testListAppCreditAccounts()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //create sample app credit group and accounts
        $credit_group = $credits_helper->defaultAppCreditGroup($user1->id);
        $account1 = $credit_group->newAccount('Test account');
        $account2 = $credit_group->newAccount('Test account 2');

        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.accounts', $credit_group->uuid));
        PHPUnit::assertGreaterThan(0, count($response['accounts']));
    }
    
    
    public function testGetCreditTxHistories()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //create sample app credit group, accounts and txs
        $credit_group = $credits_helper->defaultAppCreditGroup($user1->id);
        $account1 = $credit_group->newAccount('Test account 1');
        $account2 = $credit_group->newAccount('Test account 2');
        
        $credit_group->credit($account2->uuid, 5000);
        $credit_group->credit($account1->uuid, 2500, $account2->uuid);
        $credit_group->debit($account1->uuid, 2000);
        
        //load credit group TX history.. should be 6 entries total (2 for each tx)
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.history', $credit_group->uuid));
        PHPUnit::assertEquals(6, $response['count']);
        
        //load tx history for specific account
        $response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', route('api.credits.accounts.history', array($credit_group->uuid, $account1->uuid)));
        PHPUnit::assertEquals(2, $response['count']);
        PHPUnit::assertEquals(500, $response['account']['balance']);
    }
    
    
}
