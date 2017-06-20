<?php

use Illuminate\Support\Facades\App;
use \PHPUnit_Framework_Assert as PHPUnit;
use Mockery as m;

/*
* XChainCallbackTest
*/
class XChainCallbackTest extends TestCase {

    protected $use_database = true;

    public function testXChainBlockCallback() {
        $xchain_notification_helper = app('XChainNotificationHelper');
        $xchain_notification_helper->receiveNotificationWithWebhookController($xchain_notification_helper->sampleBlockNotification());
    }

    public function testXChainSendCallback() {
        // setup xchain and add some mock balances
        $this->setupXChainMock();
        $this->mock_builder->setBalances(['BTC' => 0.123]);

        $user = app('UserHelper')->createNewUser();
        //TODO: Build this into its own test
        $address = app('AddressHelper')->createNewAddress($user);

        $xchain_notification_helper = app('XChainNotificationHelper');
        $xchain_notification_helper->receiveNotificationWithWebhookController($xchain_notification_helper->sampleSendNotificationForAddress($address));

        $calls = $this->xchain_mock_recorder->calls;
        PHPUnit::assertEquals('confirmed', $calls[0]['data']['type']);
        PHPUnit::assertEquals('/accounts/balances/'.$address['xchain_address_id'], $calls[0]['path']);
    }

    public function testXChainReceiveCallback() {
        $xchain_notification_helper = app('XChainNotificationHelper');
        $this->setupXChainMock();

        $user = app('UserHelper')->createNewUser();
        $address = app('AddressHelper')->createNewAddress($user);

        $xchain_notification_helper->receiveNotificationWithWebhookController($xchain_notification_helper->sampleReceiveNotificationForAddress($address));
    }

    public function testTxEmailNotifications() {
        $xchain_notification_helper = app('XChainNotificationHelper');
        $this->setupXChainMock();

        \Illuminate\Support\Facades\Mail::fake();

        $user = app('UserHelper')->createNewUser();
        $address_vars['notify_email'] = 1;
        $address = app('AddressHelper')->createNewAddress($user, $address_vars);

        $xchain_notification_helper->receiveNotificationWithWebhookController($xchain_notification_helper->sampleReceiveNotificationForAddress($address));

        \Illuminate\Support\Facades\Mail::shouldReceive('send') -> once() -> with(
            'emails.tx.receive-tx',
            m::on( function(\Closure $closure) use ($user){
                $mock = m::mock('Illuminate\Mailer\Message');
                $mock -> shouldReceive('to') -> once() -> with( $user -> email )
                    -> andReturn( $mock ); //simulate the chaining
                return true;
            })
        );
    }

    ////////////////////////////////////////////////////////////////////////

    protected function setupXChainMock() {
        $this->mock_builder = app('Tokenly\XChainClient\Mock\MockBuilder');
        $this->xchain_mock_recorder = $this->mock_builder->installXChainMockClient($this);
    }


}
