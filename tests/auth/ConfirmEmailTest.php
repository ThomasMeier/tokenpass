<?php

use Carbon\Carbon;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\App;
use PHPUnit_Framework_Assert as PHPUnit;
use Tokenpass\Commands\SendUserConfirmationEmail;

/*
* ConfirmEmailTest
*/
class ConfirmEmailTest extends TestCase {

    use DispatchesJobs;

    protected $use_database = true;

    public function testConfirmUserEmail() {
        $user_helper = $this->setupUserTest();

        // create a user
        $user = $user_helper->createNewUserWithUnconfirmedEmail();

        // send a user confirmation email
        $this->dispatch(new SendUserConfirmationEmail($user));

        // get the token
        $token = $user['confirmation_code'];
        PHPUnit::assertNotEmpty($token);

        // confirm the email
        $user_helper->sendConfirmEmailRequest($token);

        // reload the user
        $user = app('Tokenpass\Repositories\UserRepository')->findById($user['id']);

        // make sure the email is confirmed and other variables are reset
        PHPUnit::assertEquals($user['email'], $user['confirmed_email']);
        PHPUnit::assertEmpty($user['confirmation_code']);
        PHPUnit::assertEmpty($user['confirmation_code_expires_at']);
    }


    public function testConfirmUserEmailErrors() {
        $user_helper = $this->setupUserTest();

        // create a user
        $user = $user_helper->createNewUserWithUnconfirmedEmail();

        // send a user confirmation email
        $this->dispatch(new SendUserConfirmationEmail($user));

        // get the token
        $token = $user['confirmation_code'];

        // check expired link
        $contents = $user_helper->sendConfirmEmailRequest('EBADBADTOKEN')->getContent();
        PHPUnit::assertContains('This email confirmation link has already been used or was not found', $contents);

        // expire link
        $user_repository = app('Tokenpass\Repositories\UserRepository');
        $user_repository->update($user, ['confirmation_code_expires_at' => Carbon::now()->modify('-13 hours')]);

        // check expired link
        $contents = $user_helper->sendConfirmEmailRequest($token)->getContent();
        PHPUnit::assertContains('confirmation link has expired', $contents);

        // reload the user
        $user = app('Tokenpass\Repositories\UserRepository')->findById($user['id']);

        // make sure the email is confirmed and other variables are reset
        PHPUnit::assertNotEquals($user['email'], $user['confirmed_email']);
        PHPUnit::assertNotEmpty($user['confirmation_code']);
        PHPUnit::assertNotEmpty($user['confirmation_code_expires_at']);
    }




    ////////////////////////////////////////////////////////////////////////


    protected function setupUserTest() {
        $user_helper = app('UserHelper')->setTestCase($this);
        return $user_helper;
    }


}
