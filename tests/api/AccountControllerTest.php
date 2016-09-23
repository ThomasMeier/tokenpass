<?php

use PHPUnit_Framework_Assert as PHPUnit;

/*
* AccountControllerTest
*/
class AccountControllerTest extends TestCase {

    protected $use_database = true;

    public function testLoginWithUsernameAndPassword() {
        $user_helper = $this->buildUserHelper();

        // create a user
        $user = $user_helper->createNewUser();

        // create an oAuth client
        $client = app('OAuthClientHelper')->createSampleOAuthClient();

        // login with username and password
        $vars = [
            'client_id' => $client['id'],
            'username'  => 'johndoe',
            'password'  => 'abc123456',
        ];
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.login'), $vars);
        PHPUnit::assertNotEmpty($response);

        PHPUnit::assertEquals('johndoe', $response['username']);
        PHPUnit::assertEquals('johndoe@tokenly.com', $response['email']);
    }

    public function testLoginAPIErrors() {
        $user_helper = $this->buildUserHelper();

        // create a user
        $user = $user_helper->createNewUser();

        // login errors
        $vars = [
            'username' => '',
            'password' => 'abc123456',
        ];
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.login'), $vars, 422);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('username field is required.', $response['message']);

        $vars = [
            'username' => 'johndoe',
            'password' => '',
        ];
        $response = app('APITestHelper')->callAPIWithoutAuthenticationAndReturnJSONContent('POST', route('api.login'), $vars, 422);
        PHPUnit::assertNotEmpty($response);
        PHPUnit::assertContains('password field is required.', $response['message']);
    }


    ////////////////////////////////////////////////////////////////////////


    protected function buildUserHelper() {
        $user_helper = app('UserHelper')->setTestCase($this);
        return $user_helper;
    }


}
