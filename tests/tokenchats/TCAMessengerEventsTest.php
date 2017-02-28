
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Events\CreditsUpdated;
use Tokenpass\Events\UserBalanceChanged;
use Tokenpass\Providers\TCAMessenger\TCAMessengerActions;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Providers\TCAMessenger\TCAMessengerRoster;
use \PHPUnit_Framework_Assert as PHPUnit;

class TCAMessengerEventsTest extends TestCase
{

    protected $use_database = true;
    protected $mock_events  = false;

    public function testSendBalancesChangedEventMessage() {
        // mock
        $messenger_mock = app('TCAMessengerHelper')->mockTCAMessengerActions();
        app('TCAMessengerHelper')->mockTCAMessengerAuth();

        // user
        $user_helper = app('UserHelper');
        $user = $user_helper->createNewUser();
        $user_channel = $user->getChannelName();

        // expect the tcaUpdated event
        $expected_message = [
            'action' => 'tcaUpdated',
            'args'   => [],
        ];
        $expected_args = ["event-{$user_channel}", $expected_message, 'tcaUpdated'];
        $messenger_mock->shouldReceive('_publish')->withArgs($expected_args)->times(1);

        // fire a tcaUpdated event
        event(new UserBalanceChanged($user));
    }

    public function testCreditsChangedEventMessage() {
        // mock
        $messenger_mock = app('TCAMessengerHelper')->mockTCAMessengerActions();
        // app('TCAMessengerHelper')->debugPublish($messenger_mock);
        app('TCAMessengerHelper')->mockTCAMessengerAuth();

        $user_helper = app('UserHelper');
        $user = $user_helper->createRandomUser();
        $user_channel = $user->getChannelName();
        $user_2 = $user_helper->createRandomUser();
        $user_2_channel = $user_2->getChannelName();
        $credit_group = app('AppCreditsHelper')->newAppCreditGroup($user, ['event_slug' => 'app-rev', 'publish_events' => true]);
        $user_account = app('AppCreditsHelper')->newAppCreditAccountForUser($user, $credit_group);
        app('AppCreditsHelper')->creditAccount(101, $credit_group, $user_account);

        // expect the creditsUpdated event
        $expected_message = [
            'action' => 'creditsUpdated',
            'args' => [
                'slug' => 'app-rev',
                'balance' => '101',
            ],
        ];
        $expected_args = ["event-{$user_channel}", $expected_message, 'creditsUpdated'];
        $messenger_mock->shouldReceive('_publish')->withArgs($expected_args)->times(1);

        // send a credit update
        event(new CreditsUpdated($user_account));

    }

}