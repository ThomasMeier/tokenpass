<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Models\AppCreditAccount;
use Tokenpass\Models\AppCreditTransaction;
use Tokenpass\Models\AppCredits;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\Provisional;

/*
* WebAppCreditsTest
*/
class WebAppCreditsTest extends TestCase {

    protected $use_database = true;
    
    public function testWebAppTransferCredits()
    {
        //setup testing stuff
        $user_helper = app('UserHelper')->setTestCase($this);
        $user1 = $user_helper->createRandomUser();
        $oauth_client = app('OAuthClientHelper')->createConnectedOAuthClientWithTCAScopes($user1);
        // $api_tester = app('OAuthClientAPITester')->be($oauth_client);        
        $credits_helper = app('AppCreditsHelper');
        
        //create sample app credit group, accounts and txs
        $credit_group = $credits_helper->defaultAppCreditGroup($user1->id);
        $account1 = $credit_group->newAccount('Test account 1');
        $account2 = $credit_group->newAccount('Test account 2');
        
        // setup samples
        $credit_group->credit($account1->uuid, 2500);
        $credit_group->credit($account2->uuid, 5000);
        
        // do a web transfer
        Auth::setUser($user1);
        $parameters = ['from' => $account1->name, 'to' => $account2->name, 'amount' => 200, 'ref' => 'fooref'];
        // echo "\route('app-credits.transfer', ['uuid' => \$credit_group->uuid]): ".json_encode(route('app-credits.transfer', ['uuid' => $credit_group->uuid]), 192)."\n";
        $response = $this->call('POST', route('app-credits.transfer', ['uuid' => $credit_group->uuid]), $parameters);
        PHPUnit::assertEquals('alert-success', Session::get('message-class'));
        PHPUnit::assertEquals(302, $response->getStatusCode(), Session::get('message'));

        // check balances
        PHPUnit::assertEquals(2300, AppCreditTransaction::where('app_credit_account_id', $account1->id)->sum('amount'));
        PHPUnit::assertEquals(5200, AppCreditTransaction::where('app_credit_account_id', $account2->id)->sum('amount'));

    }

    // ------------------------------------------------------------------------
    
    protected function runRequest($request) {
        $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        return $response;
    }


}
