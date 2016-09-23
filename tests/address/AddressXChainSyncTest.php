<?php

use Illuminate\Support\Facades\App;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* AddressXChainSyncTest
*/
class AddressXChainSyncTest extends TestCase {

    protected $use_database = true;

    public function testSyncAddressWithXChain() {
        $this->setupXChainMock();

        $user = app('UserHelper')->createNewUser();
        $address = app('AddressHelper')->createNewAddressWithoutXChainIDs($user);

        // add some mock balances
        $this->mock_builder->setBalances(['BTC' => 0.123]);

        $address->syncWithXChain();

        // make sure the correct calls were made
        $calls = $this->xchain_mock_recorder->calls;
        PHPUnit::assertEquals('/unmanaged/addresses', $calls[0]['path']);
        PHPUnit::assertEquals('/monitors', $calls[1]['path']);
        PHPUnit::assertEquals('send', $calls[1]['data']['monitorType']);
        PHPUnit::assertEquals('/monitors', $calls[2]['path']);
        PHPUnit::assertEquals('receive', $calls[2]['data']['monitorType']);
        PHPUnit::assertEquals('confirmed', $calls[3]['data']['type']);
    }

    ////////////////////////////////////////////////////////////////////////

    protected function setupXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
    }


}
