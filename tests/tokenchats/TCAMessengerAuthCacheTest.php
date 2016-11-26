
<?php

use Illuminate\Support\Facades\Session;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use \PHPUnit_Framework_Assert as PHPUnit;

class TCAMessengerAuthCacheTest extends TestCase
{

    protected $use_database = true;

    public function testTCAMessengerTokenpassGrantCache() {
        // mock
        $tca_messenger_auth = Mockery::mock(TCAMessengerAuth::class)->makePartial();
        $tca_messenger_auth->shouldReceive('grant')->once()->andReturn([
            'level' => 'channel',
            'subscribe_key' => 'sub-c-foo',
            'channels' => ['etc'],
        ]);
        $tca_messenger_auth->tokenpass_auth_key = 'tokenpass_auth_key_TEST';

        // insert tokenpass auth
        $channel = 'testchannel1';
        $tca_messenger_auth->authorizeTokenpass($read=true, $write=true, $channel);
        $result = (array)DB::table('pubnub_tokenpass_access')->where([
            ['channel', '=', $channel],
            ['read',    '=', true],
            ['write',   '=', true],
            ['ttl',     '=', 0],
        ])->first();
        PHPUnit::assertNotEmpty($result);
        PHPUnit::assertEquals($channel, $result['channel']);
        PHPUnit::assertTrue($tca_messenger_auth->tokenpassGrantExists($read, $write, $channel, 0));

        // re-insert tokenpass auth
        $channel = 'testchannel1';
        $tca_messenger_auth->authorizeTokenpass($read=true, $write=true, $channel);
    }

    public function testTCAMessengerUserGrantCache() {
        // mock
        $tca_messenger_auth = Mockery::mock(TCAMessengerAuth::class)->makePartial();
        $tca_messenger_auth->shouldReceive('grant')->once()->andReturn([
            'level' => 'channel',
            'subscribe_key' => 'sub-c-foo',
            'channels' => ['etc'],
        ]);
        $tca_messenger_auth->shouldReceive('revoke')->once()->andReturn(true);
        $tca_messenger_auth->tokenpass_auth_key = 'tokenpass_auth_key_TEST';


        // user
        $user_helper = app('UserHelper')->setTestCase($this);
        $user = $user_helper->createNewUser();


        // insert user auth
        $channel = 'testchannel2';
        $tca_messenger_auth->authorizeUser($user, $read=true, $write=true, $channel);
        $result = (array)DB::table('pubnub_user_access')->where([
            ['user_id', '=', $user['id']],
            ['channel', '=', $channel],
            ['read',    '=', true],
            ['write',   '=', true],
            ['ttl',     '=', 0],
        ])->first();
        PHPUnit::assertNotEmpty($result);
        PHPUnit::assertEquals($channel, $result['channel']);
        PHPUnit::assertTrue($tca_messenger_auth->userGrantExists($user, $read, $write, $channel, 0));

        // re-insert user auth
        $tca_messenger_auth->authorizeUser($user, $read=true, $write=true, $channel);
 
        // revoke
        $tca_messenger_auth->revokeUser($user, $channel);

        // ensure it is gone
        $result = (array)DB::table('pubnub_user_access')->where([
            ['user_id', '=', $user['id']],
            ['channel', '=', $channel],
            ['read',    '=', true],
            ['write',   '=', true],
            ['ttl',     '=', 0],
        ])->first();
        PHPUnit::assertEmpty($result);
   }

}