<?php

use Tokenpass\OAuth\Facade\OAuthGuard;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* OAuthGuardTest
*/
class OAuthGuardTest extends TestCase {

    protected $use_database = true;

    public function testOAuthGuardEmpty() {
        OAuthGuard::applyUserByOauthAccessToken('foo');
        PHPUnit::assertEmpty(OAuthGuard::user());
        PHPUnit::assertEmpty(OAuthGuard::session());
    }

    public function testOAuthGuardByToken() {
        $oauth_helper = app('OAuthClientHelper');

        $user = app('UserHelper')->getOrCreateSampleUser();
        $other_user = app('UserHelper')->createRandomUser();
        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);

        // random user
        $other_token = $oauth_helper->connectUserSession($other_user, $oauth_client);
        PHPUnit::assertNotEmpty($other_token);

        // standard user
        $token = $oauth_helper->connectUserSession($user, $oauth_client);
        PHPUnit::assertNotEmpty($token);

        // apply the user
        OAuthGuard::applyUserByOauthAccessToken($token);

        PHPUnit::assertNotEmpty(OAuthGuard::session());
        PHPUnit::assertNotEmpty(OAuthGuard::user());
        PHPUnit::assertEquals($user['id'], OAuthGuard::user()['id']);

        // check the scopes
        PHPUnit::assertTrue(OAuthGuard::hasScope('tca'));
        PHPUnit::assertTrue(OAuthGuard::hasScope('private-address'));
        PHPUnit::assertFalse(OAuthGuard::hasScope('foo'));
    }

    public function testOAuthGuardExpiredAccessToken() {
        $oauth_helper = app('OAuthClientHelper');
        $user = app('UserHelper')->getOrCreateSampleUser();

        $oauth_client = $oauth_helper->createConnectedOAuthClientWithTCAScopes($user);
        // create an expired access token
        $token = $oauth_helper->connectUserSession($user, $oauth_client, null, ['expire_time_ttl' => -100]);

        // apply the user
        OAuthGuard::applyUserByOauthAccessToken($token);

        // no user should be found
        PHPUnit::assertEmpty(OAuthGuard::user());
        PHPUnit::assertFalse(OAuthGuard::hasScope('tca'));
    }


}
