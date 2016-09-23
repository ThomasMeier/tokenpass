<?php

use Illuminate\Support\Facades\App;

/*
* DashboardTest
*/
class DashboardTest extends TestCase {


    protected $use_database = true;

    public function testDashboardShowsName() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user = $user_helper->loginWithForm($this->app);

        // can get to dashboard
        $response = $this->call('GET', '/dashboard');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('johndoe', $response->getContent());
        // $this->assertContains('johndoe@tokenly.com', $response->getContent());
    }



}
