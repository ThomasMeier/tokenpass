<?php

use Illuminate\Support\Facades\App;

/*
* RegistrationTest
*/
class RegistrationTest extends TestCase {

    protected $use_database = true;

    public function testRegisterUser() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a user
        $user = $user_helper->registerNewUser($this->app);
        $this->assertNotNull($user);

        // make sure the user exists
        $this->assertTrue($user_helper->userExistsInDB($user));
    }

    public function testRegistrationLogsInUser() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a user
        $user = $user_helper->registerNewUser($this->app);
        $this->assertNotNull($user);

        // make sure user is logged in
        $user_id = $this->app['auth']->id();
        $this->assertGreaterThan(0, $user_id);

    }


    // test email conflict
    public function testRegisterUserConflictingEmail() {
        // setup
        $user_helper = $this->setupUserTest();

        // create a second new user
        $user = $user_helper->createNewUser([
            'name'     => 'Jane Doe',
            'username' => 'janedoe',
            'email'    => 'janedoe@tokenly.com',
            'password' => 'xyz123456',
        ]);

        // try to change to a conflicting email address
        $this->verifyRegisterError($user_helper, ['email' => 'janedoe@tokenly.com'], 'email has already been taken');

    }

    // test empty username
    public function testEmptyUsernameForRegisterUser() {
        // setup
        $user_helper = $this->setupUserTest();

        // post form
        $this->verifyRegisterError($user_helper, ['name' => 'Chicken Little', 'username' => '',], 'username field is required.');
    }


    // test register with conflicting username
    public function testRegisterUserWithConflictingUsername() {
        // setup
        $user_helper = $this->setupUserTest();

        // create a second new user
        $user = $user_helper->createNewUser([
            'name'     => 'Jane Doe',
            'username' => 'janedoe',
            'email'    => 'janedoe@tokenly.com',
            'password' => 'xyz123456',
        ]);

        // try to change to a conflicting username address
        $this->verifyRegisterError($user_helper, ['username' => 'janedoe'], 'username has already been taken');

    }



    ////////////////////////////////////////////////////////////////////////


    protected function setupUserTest() {
        $user_helper = app('UserHelper')->setTestCase($this);
        return $user_helper;
    }

    protected function verifyRegisterError($user_helper, $vars, $expected_error_string) {
        // get the register form
        $response = $this->call('GET', '/auth/register');
        $this->assertEquals(200, $response->getStatusCode());

        // post to the register form
        $user_helper->verifyErrorForRegisterWithForm($this->app, $vars, $expected_error_string);
    }

}
