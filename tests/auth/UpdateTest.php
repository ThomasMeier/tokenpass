<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* UpdateTest
*/
class UpdateTest extends TestCase {

    protected $use_database = true;

    public function testUpdateName() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $updated_user = $this->getAndPostUpdateForm($user_helper, ['name' => 'Chicken Little', 'new_password' => '']);

        PHPUnit::assertEquals('Chicken Little', $updated_user['name']);


        // make sure we can still login
        $this->app['auth']->logout();
        $user_helper->loginWithForm($this->app, ['password' => 'abc123456', ]);
    }

    // test empty email
    public function testEmptyEmailForUpdateUser() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $this->verifyUpdateError($user_helper, ['name' => 'Chicken Little', 'email' => '',], 'Email is requred');
    }

    // test password don't match
    public function testPasswordsUnmatchedForUpdateUse() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $this->verifyUpdateError($user_helper, ['name' => 'Chicken Little', 'password' => 'WRONG_PASSWORD'], 'provide the correct password');
    }


    // test change email
    public function testUpdateUserChangeEmail() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $updated_user = $this->getAndPostUpdateForm($user_helper, ['email' => 'chickenlittle@tokenly.com']);

        PHPUnit::assertEquals('chickenlittle@tokenly.com', $updated_user['email']);
    }


    // test change email
    public function testUpdateUserConflictingEmail() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // create a second new user
        $user = $user_helper->createNewUser([
            'name'     => 'Jane Doe',
            'username' => 'janedoe',
            'email'    => 'janedoe@tokenly.com',
            'password' => 'xyz123456',
        ]);

        // try to change to a conflicting email address
        $this->verifyUpdateError($user_helper, ['email' => 'janedoe@tokenly.com'], 'email has already been taken');

    }

    // test change username
    public function testUpdateUserCannotChangeUsername() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $updated_user = $this->getAndPostUpdateForm($user_helper, ['username' => 'chickenlittle']);

        PHPUnit::assertEquals('johndoe', $updated_user['username']);
    }


    // test empty password
    public function testUpdateUserEmptyPassword() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $this->verifyUpdateError($user_helper, ['name' => 'Chicken Little', 'password' => null], 'password field is required');
    }


    // test change password and still logs in
    public function testUpdateUserChangePasswordAndStillLogsIn() {
        // setup
        list($user, $user_helper) = $this->setupUserTest();

        // post form
        $this->getAndPostUpdateForm($user_helper, ['new_password' => 'theskyisfalling', ]);

        // logout
        $this->app['auth']->logout();

        // login
        $user_helper->loginWithForm($this->app, ['password' => 'theskyisfalling', ]);
    }

    public function testStore() {
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();
        $user_helper->loginWithForm($this->app);

        $incorrect = '.deploy.sh';
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($incorrect, null, 'application/nonsense', null, null, true);

        // Attempt to upload an incompatible file
        $response = $this->call('POST',
            '/image/store',
            array($file),
            array(),
            array('file' => $file),
            ['CONTENT_TYPE' => 'application/nonsense'],
            ['Content-Type' =>'application/nonsense']);

        PHPUnit::assertContains('Only image type files are accepted as an avatar.', $response->getContent());

        $image = 'public/img/landing_hero.png';

        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($image, null, 'image/png', null, null, true);

        // Attempt upload a real image,
        $response = $this->call('POST',
            '/image/store',
            array($file),
            array(),
            array('file' => $file),
            ['CONTENT_TYPE' => 'image/png'],
            ['Content-Type' =>'image/png']);

        // Removed until built mock for  S3
        //PHPUnit::assertContains('Avatar defined.', $response->getContent());
    }

    ////////////////////////////////////////////////////////////////////////

    protected function setupUserTest() {
        $user_helper = app('UserHelper')->setTestCase($this);

        // create a new user and login
        $user = $user_helper->createNewUser();
        $user_helper->loginUser($this->app, $user);

        return [$user, $user_helper];
    }

    protected function getAndPostUpdateForm($user_helper, $vars) {
        // get the update form
        $response = $this->call('GET', '/auth/update');
        $this->assertEquals(200, $response->getStatusCode());

        // post to the update form
        $updated_user = $user_helper->updateWithForm($this->app, $vars);

        return $updated_user;
    }

    protected function verifyUpdateError($user_helper, $vars, $expected_error_string) {
        // get the update form
        $response = $this->call('GET', '/auth/update');
        $this->assertEquals(200, $response->getStatusCode());

        // post to the update form
        $user_helper->verifyErrorForUpdateWithForm($this->app, $vars, $expected_error_string);
    }



}
