<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

use \PHPUnit_Framework_Assert as PHPUnit;

/*
* LoginTest
*/
class LoginTest extends TestCase {

    protected $use_database = true;

    public function testUserLogin() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a user
        $user = $user_helper->createNewUser();
        PHPUnit::assertNotNull($user);

        // login
        $user = $user_helper->loginWithForm($this->app);
        PHPUnit::assertNotNull($user);

        // check session
        $user_id = $this->app['auth']->id();
        PHPUnit::assertGreaterThan(0, $user_id);

        // check db
        PHPUnit::assertTrue($user_helper->userExistsInDB($user));
    }

    public function testUserEmailBasedLogin() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a user
        $user = $user_helper->createNewUser();
        PHPUnit::assertNotNull($user);

        $form_vars = [
            'username' => $user['email'],
            'password' => $user_helper->defaultUserVars()['password'],
        ];

        $response = $this->call('POST', '/auth/login', array_merge($form_vars, ['_token' => true,]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());
                throw new Exception("Login Found RedirectResponse with errors: ".$errors, 1);
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

    }

    public function testUserLoginError() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a user
        $user = $user_helper->createNewUser();
        PHPUnit::assertNotNull($user);

        // login
        $response = $this->call('POST', '/auth/login', ['username' => 'wrong', 'password' => 'wrong', '_token' => true,]);

        if ($response instanceof Illuminate\Http\RedirectResponse) {
            $found_error = false;
            if ($errors_bag = $response->getSession()->get('errors')) {
                $found_error = true;
                $errors = implode(", ", $errors_bag->all());
                PHPUnit::assertContains('credentials do not match our records', $errors);
            }

            if (!$found_error) {
                throw new Exception("Failed to find error", 1);
            }
        } else {
            throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1);
        }
    }


    public function testLoginRedirectsToPreviousPage() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user
        $user = $user_helper->createNewUser();
        PHPUnit::assertNotNull($user);


        // try to hit user dashboard
        $response = $this->call('GET', '/dashboard');

        // check that it is a 302 redirect to the login page
        PHPUnit::assertEquals(302, $response->getStatusCode());
        PHPUnit::assertEquals('http://localhost/auth/login', $response->getTargetUrl());

        // get the last sesssion
        $session = $this->app['auth']->getSession();

        // login
        $response = $user_helper->sendLoginRequest($this->app, $session);
        PHPUnit::assertEquals(302, $response->getStatusCode());
        PHPUnit::assertEquals('http://localhost/dashboard', $response->getTargetUrl());

    }


    public function testDashboardAccess() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // guest should not have access to dashboard
        $response = $this->call('GET', '/dashboard');
        PHPUnit::assertEquals(302, $response->getStatusCode());
        PHPUnit::assertEquals('http://localhost/auth/login', $response->getTargetUrl());

        // create a user
        $user = $user_helper->createNewUser();
        PHPUnit::assertNotNull($user);

        // login
        $user = $user_helper->loginWithForm($this->app);
        PHPUnit::assertNotNull($user);

        // can get to dashboard
        $response = $this->call('GET', '/dashboard');
        PHPUnit::assertEquals(200, $response->getStatusCode());

    }



}
