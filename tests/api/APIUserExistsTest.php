<?php

use PHPUnit_Framework_Assert as PHPUnit;

/*
* APIUserExistsTest
*/
class APIUserExistsTest extends TestCase {


    protected $use_database = true;

   public function testCheckUserExists() {
        $user_helper    = app('UserHelper')->setTestCase($this);

        // create a user
        $users = [];
        $users[0] = $user_helper->createRandomUser();
        $users[1] = $user_helper->createRandomUser();

        // setup api client
        $api_tester = app('APITestHelper');
        $api_tester->be($users[0]);

        // require authentication
        $response = $api_tester->callAPIWithoutAuthenticationAndReturnJSONContent('GET', route('api.lookup.user.check-exists', [
            'username' => $users[1]['username'],
        ]), [], 403);
        
        //check user exists
        $response = $api_tester->callJSON('GET', route('api.lookup.user.check-exists', [
            'username' => $users[1]['username']
        ]));   
        PHPUnit::assertTrue($response['result']);
        
        //check via email
        $response = $api_tester->callJSON('GET', route('api.lookup.user.check-exists', [
            'username' => $users[1]['email']
        ]));   
        PHPUnit::assertTrue($response['result']);        
        
        
        //try a bogus request
        $response = $api_tester->callJSON('GET', route('api.lookup.user.check-exists', [
            'username' => 'fake dude'
        ]));   
        PHPUnit::assertFalse($response['result']);        
        
        //try with real id_hash
        $id_hash = hash('sha256', $users[1]['uuid']);
        $response = $api_tester->callJSON('GET', route('api.lookup.user.check-exists', [
            'username' => $users[1]['username'], 'id_hash' => $id_hash
        ]));   
        PHPUnit::assertTrue($response['result']);        
        
        
        //try with fake id_hash
        $response = $api_tester->callJSON('GET', route('api.lookup.user.check-exists', [
            'username' => $users[1]['username'], 'id_hash' => 'fake'
        ]));   
        PHPUnit::assertFalse($response['result']);                

    }

}
