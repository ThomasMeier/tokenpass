<?php

use TKAccounts\Models\User;
use TKAccounts\Models\Address;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/*
* UserHelper
*/
class UserHelper
{
    public function __construct(SessionManager $session_manager) {
        $this->session_manager = $session_manager;
    }

    public function setTestCase(TestCase $test_case) {
        $this->test_case = $test_case;
        return $this;
    }


    public function registerNewUser($app, $user_override_vars = [])
    {
        $user = null;

        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);

        $response = $this->test_case->call('POST', '/auth/register', array_merge($user_vars, ['_token' => true, 'password_confirmation' => $user_vars['password']]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());
                throw new Exception("Found RedirectResponse with errors: ".$errors, 1);
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        // get the session
        $auth = $app['auth'];
        $user_id = $auth->id();

        // get the user just created
        $user = app('TKAccounts\Repositories\UserRepository')->findByID($user_id);
        if (!$user OR !$user->getKey()) {
            return null;
        }

        return $user;
    }

    public function verifyErrorForRegisterWithForm($app, $user_override_vars = [], $expected_error_string)
    {
        $user = null;

        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);

        // unset null override vars
        foreach (array_keys($user_override_vars) as $key) { if ($user_override_vars[$key] === null) { unset($user_vars[$key]); } }

        // set password_confirmation
        if (!isset($user_vars['password_confirmation'])) { $user_vars['password_confirmation'] = $user_vars['password']; }

        $response = $this->test_case->call('POST', '/auth/register', array_merge($user_vars, ['_token' => true,]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());

                if (strpos($errors, $expected_error_string) === false) {
                    throw new Exception("Did not found expected error $expected_error_string in ".$errors, 1);
                } else {
                    // found error - so all is good
                    return;
                }
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        // if we didn't get an expected error, throw an exception
        if ($expected_error_string !== null) { throw new Exception("Did not find expected error: $expected_error_string", 1); }
    }



    public function updateWithForm($app, $user_override_vars = [])
    {
        $user = null;

        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);
        if (!isset($user_vars['new_password_confirmation']) AND isset($user_vars['new_password'])) {
            $user_vars['new_password_confirmation'] = $user_vars['new_password'];
        }

        $response = $this->test_case->call('POST', '/auth/update', array_merge($user_vars, ['_token' => true,]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());
                throw new Exception("Found RedirectResponse with errors: ".$errors, 1);
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        // get the session
        $auth = $app['auth'];
        $user_id = $auth->id();

        // get the user just updated
        $user = app('TKAccounts\Repositories\UserRepository')->findByID($user_id);
        if (!$user) { throw new Exception("No user found", 1); }
        return $user;
    }

    public function verifyErrorForUpdateWithForm($app, $user_override_vars = [], $expected_error_string)
    {
        $user = null;

        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);

        // unset null override vars
        foreach (array_keys($user_override_vars) as $key) { if ($user_override_vars[$key] === null) { unset($user_vars[$key]); } }

        if (!isset($user_vars['new_password_confirmation']) AND isset($user_vars['new_password'])) {
            $user_vars['new_password_confirmation'] = $user_vars['new_password'];
        }

        $response = $this->test_case->call('POST', '/auth/update', array_merge($user_vars, ['_token' => true,]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());

                if (strpos($errors, $expected_error_string) === false) {
                    throw new Exception("Did not found expected error $expected_error_string in ".$errors, 1);
                } else {
                    // found error - so all is good
                    return;
                }
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        // if we didn't get an expected error, throw an exception
        if ($expected_error_string !== null) { throw new Exception("Did not find expected error: $expected_error_string", 1); }
    }


    public function createNewUserWithUnconfirmedEmail($user_override_vars = []) {
        $user_vars = array_merge($this->defaultUserVars(), ['confirmed_email' => null], $user_override_vars);
        return $this->createNewUser($user_vars);
    }

    public function getOrCreateSampleUser() {
        $email = $this->defaultUserVars()['email'];
        $user = app('TKAccounts\Repositories\UserRepository')->findByEmail($email);
        if (!$user) {
            $user = $this->createNewUser();
        }
        return $user;
    }

    public function createNewUser($user_override_vars = []) {
        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);

        // unset null vars
        foreach($user_override_vars as $key => $val) { if ($val === null) { unset($user_vars[$key]); } }

        // create the user (this also hashes the password)
        $user = app('TKAccounts\Repositories\UserRepository')->create($user_vars);
        if (!$user->getKey()) { return null; }

        // get the user just created
        return $user;
    }

    public function createAltUser($user_override_vars = []) {
        $user_vars = array_merge($this->altUserVars(), $user_override_vars);

        // unset null vars
        foreach($user_override_vars as $key => $val) { if ($val === null) { unset($user_vars[$key]); }
        }

        // create the user (this also hashes the password)
        $user = app('TKAccounts\Repositories\UserRepository')->create($user_vars);
        if (!$user->getKey()) {
        return null;
        }

        // get the user just created
        return $user;

    }

    public function loginWithForm($app, $user_override_vars = [])
    {
        $user = null;

        // get the response
        $response = $this->sendLoginRequest($app, null, $user_override_vars);

        // get the user id
        $auth = $app['auth'];
        $user_id = $auth->id();

        // get the user just created
        $user = app('TKAccounts\Repositories\UserRepository')->findByID($user_id);
        if (!$user OR !$user->getKey()) {
            return null;
        }

        return $user;
    }

    public function loginUser($app, $user)
    {
        $app['auth']->login($user);
    }

    public function sendBitcoinLoginRequest($data) {

        if($data == true) {
            $response = $this->test_case->action('POST', 'Auth\AuthLoginController@postBitcoinLogin', array(), array(
                'sigval' => 'Y7d8f868f2879aaf5ed4e0fefd4472ff',
                'address' => '17jt7kJJQPMqJwTVCKRWjLBdYcS888t3CU',
                'sig' => 'G1Cm1DlfG85N81Fjle6bxxq4U84NhBe392JP6qawk0iuN2exif7IRvlQ4C54N9iEASYsi4qKeMEjovavPmn10sE='));
        } else {
            $response = $this->test_case->action('POST', 'Auth\AuthLoginController@postBitcoinLogin', array(), array(
                'sigval' => 'Invalid Sigval',
                'address' => '1FakeAddressYes',
                'sig' => 'NotARealSigoesnorbgairbgirbdgih43t35'));
        }

        return $response;
    }

    public function sendLoginRequest($app, $session=null, $user_override_vars = []) {
        $user_vars = array_merge($this->defaultUserVars(), $user_override_vars);
        $form_vars = [
            'username' => $user_vars['username'],
            'password' => $user_vars['password'],
        ];


        $response = $this->test_case->call('POST', '/auth/login', array_merge($form_vars, ['_token' => true,]));
        if ($response instanceof Illuminate\Http\RedirectResponse) {
            if ($errors_bag = $response->getSession()->get('errors')) {
                $errors = implode(", ", $errors_bag->all());
                throw new Exception("Login Found RedirectResponse with errors: ".$errors, 1);
            }
        }
        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        return $response;



        // // Request::create($uri, $method, $parameters, $cookies, $files, $server, $content)
        // $request = Request::create('/auth/login', 'POST', array_merge($form_vars, ['_token' => true]));

        // // ensure session
        // if (!$session) {
        //     $session = $this->session_manager->driver();
        //     $session->start();
        // }
        // $request->setSession($session);


        // // send the login request with the test kernel which does not read the session
        // $kernel = $app->make('TKAccounts\TestHelpers\TestKernel');
        // return $kernel->handle($request);
    }


    public function sendConfirmEmailRequest($token, $session=null) {
        // $response = $this->test_case->call('GET', '/auth/verify/'.$token);
        $response = $this->test_case->call('GET', '/auth/verify/'.$token);

        // if ($errors_bag = $response->getSession()->get('errors')) {
        //     $errors = implode(", ", $errors_bag->all());
        //     throw new Exception("Found Response with errors: ".$errors, 1);
        // }

        if (substr($response->getStatusCode(), 0, 1) == '5') { throw new Exception("Found unexpected response with status code ".$response->getStatusCode(), 1); }

        return $response;
    }

    public function userExistsInDB(User $user) {
        $loaded_user = app('TKAccounts\Repositories\UserRepository')->findByID($user->getKey());
        return ($loaded_user->getKey() AND $loaded_user->getKey() == $user->getKey());
    }

    public function defaultUserVars() {
        return [
            'name'            => 'John Doe',
            'username'        => 'johndoe',
            'email'           => 'johndoe@tokenly.com',
            'confirmed_email' => 'johndoe@tokenly.com',
            'password'        => 'abc123456',
            'second_factor'   => '0'
        ];
    }

    public function altUserVars() {
        return [
            'name'            => 'Jane Doe',
            'username'        => 'janedoe',
            'email'           => 'janedoe@tokenly.com',
            'confirmed_email' => 'janedoe@tokenly.com',
            'password'        => 'abc123456',
        ];
    }
    
    public function randomUserVars() {
        $username = str_replace(' ', '_', Address::getSecureCodeGeneration(null, null, true));
        return [
            'name'            => '',
            'username'        => $username,
            'email'           => $username.'@tokenly.com',
            'confirmed_email' => $username.'@tokenly.com',
            'password'        => 'abc123456',
        ];
    }
}
