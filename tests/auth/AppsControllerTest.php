<?php

use \PHPUnit_Framework_Assert as PHPUnit;
use Illuminate\Support\Facades\App;
use Tokenpass\Models\OAuthClient;


/*
* OAuthTest
*/
class AppsControllerTest extends TestCase
{


    protected $use_database = true;

    public function testAppFormGet() {

        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);
        $address_helper->createNewAddress($user);

        // check loading authorize form
        $response = $this->call('GET', '/auth/apps');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('<section id="appsController">', $response->getContent());
    }

    public function testRegisterApp() {

        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);
        $address_helper->createNewAddress($user);

        $no_name = $this->call('POST', '/auth/apps/new', array() , array());
        PHPUnit::assertContains('Client name required', Session::get('message'));
        PHPUnit::assertEquals(302 , $no_name->status());

        $no_app_url = $this->call('POST', '/auth/apps/new', array(
            'name'  =>  'joeblogs'
        ) , array());
        PHPUnit::assertContains('Please add an app URL', Session::get('message'));
        PHPUnit::assertEquals(302 , $no_app_url->status());

        $bad_app_url = $this->call('POST', '/auth/apps/new', array(
            'name'  =>  'joeblogs',
            'app_link' => 'htttp:/w:..2'
        ) , array());

        PHPUnit::assertContains('Please enter a valid app URL', Session::get('message'));
        PHPUnit::assertEquals(302 , $bad_app_url->status());

        $correct = $this->call('POST', '/auth/apps/new', array(
            'name'  =>  'joeblogs',
            'app_link' => 'http://bit.split/call'
        ) , array());

        PHPUnit::assertContains('Client application registered!', Session::get('message'));
        PHPUnit::assertEquals(302 , $correct->status());
    }

    public function testUpdateApp() {

        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);
        $address_helper->createNewAddress($user);

        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient([
            'user_id' => '1'
        ]);

        $incorrect = $this->call('POST', '/auth/apps/WRONG_KEY/edit', array(
            'name'  =>  'tina',
            'app_link' => 'http://bit.split/call'
        ) , array());

        PHPUnit::assertContains('Client application not found', Session::get('message'));
        PHPUnit::assertEquals(302 , $incorrect->status());

        $correct = $this->call('POST', '/auth/apps/MY_API_TOKEN/edit', array(
            'name'  =>  'tina',
            'app_link' => 'http://bit.split/call'
        ) , array());

        PHPUnit::assertContains('Client application updated.', Session::get('message'));
        PHPUnit::assertEquals(302 , $correct->status());
    }

    public function testRegenerateApp() {
        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);
        $address_helper->createNewAddress($user);

        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient([
            'user_id' => '1'
        ]);

        $correct = $this->call('GET', '/auth/apps/MY_API_TOKEN/new', array(
            'name'  =>  'tina',
            'app_link' => 'http://bit.split/call'
        ) , array());

        $correct = $this->call('PATCH', '/auth/apps/MY_API_TOKEN/regen', array() , array());
        $result =  DB::table('oauth_clients')->first();
        
        PHPUnit::assertStringStartsNotWith('MY_API_TOKEN', $result->id);
        PHPUnit::assertContains('Client application updated.', Session::get('message'));
        PHPUnit::assertEquals(302 , $correct->status());
    }

    public function testDeleteApp() {

        $address_helper = app('AddressHelper');
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);
        $address_helper->createNewAddress($user);

        $oauth_client = app('OAuthClientHelper')->createSampleOAuthClient([
            'user_id' => '1'
        ]);

        $incorrect = $this->call('GET', '/auth/apps/WRONG_KEY/delete', array(
            'name'  =>  'tina',
            'app_link' => 'http://bit.split/call'
        ) , array());

        PHPUnit::assertContains('Client application not found', Session::get('message'));
        PHPUnit::assertEquals(302 , $incorrect->status());

        $correct = $this->call('GET', '/auth/apps/MY_API_TOKEN/delete', array(
            'name'  =>  'tina',
            'app_link' => 'http://bit.split/call'
        ) , array());

        PHPUnit::assertContains('Client application deleted!', Session::get('message'));
        PHPUnit::assertEquals(302 , $correct->status());
    }
}