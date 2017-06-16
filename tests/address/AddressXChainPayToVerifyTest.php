<?php

use Illuminate\Support\Facades\App;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* AddressXChainSyncTest
*/
class AddressXChainPayToVerifyTest extends TestCase {

    protected $use_database = true;

    public function testSetUpPayToVerifyMethod() {
        $this->setupXChainMock();

        $user = app('UserHelper')->createNewUser();
        $address = app('AddressHelper')->createNewAddressWithoutXChainIDs($user);


        $address->setUpPayToVerifyMethod();

        $calls = $this->xchain_mock_recorder->calls;
        PHPUnit::assertEquals('/addresses', $calls[0]['path']);

    }
    ////////////////////////////////////////////////////////////////////////

    protected function setupXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
        return $this->xchain_mock_recorder;
    }


}