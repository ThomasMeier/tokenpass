
<?php

use \PHPUnit_Framework_Assert as PHPUnit;

class CivicAuthTest extends TestCase {

    protected $use_database = true;


    public function testUserRegistrationWithCivic() {
        $this->setUpMock();
        $user_helper = app('UserHelper')->setTestCase($this);
        $response = $user_helper->sendCivicLoginRequest();

        PHPUnit::assertNull(\Illuminate\Support\Facades\Auth::user());

        $user = $user_helper->registerNewUser($this->app);
        PHPUnit::assertEquals(1, $user->civic_enabled);

        \Illuminate\Support\Facades\Auth::logout();

        PHPUnit::assertNull(\Illuminate\Support\Facades\Auth::user());

        $response = $user_helper->sendCivicLoginRequest();
        PHPUnit::assertNotNull(\Illuminate\Support\Facades\Auth::user());
    }

    public function testLoginWithCivic() {
    }

    function setUpMock() {
        $object = new \Blockvis\Civic\Sip\UserData('1234455', array(array('label' => 'email', 'value' => 'test@tokenly.com', 'isValid' => true, 'isOwner' => true)));

        $user_mock = Mockery::mock(Blockvis\Civic\Sip\Client::class);
        $user_mock->shouldReceive('exchangeToken')->andReturn($object);
        $this->app->instance(Blockvis\Civic\Sip\Client::class, $user_mock);
    }



}
