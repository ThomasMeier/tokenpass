<?php

namespace Tokenpass\Providers\TCAMessenger\Provider;

use Exception;
use Illuminate\Support\ServiceProvider;
use Pubnub\Pubnub;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Providers\TCAMessenger\TCAMessengerRoster;

class TCAMessengerServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Tokenpass\Providers\TCAMessenger\TCAMessenger', function ($app) {
            return new TCAMessenger(
                app('Tokenly\BvamApiClient\BVAMClient'),
                app('Tokenpass\Providers\TCAMessenger\TCAMessengerAuth'),
                app('Tokenpass\Providers\TCAMessenger\TCAMessengerRoster'),
                app('Tokenpass\Providers\TCAMessenger\TCAMessengerActions'),
                app('Pubnub\Pubnub')
            );
        });

        $this->app->bind('Tokenpass\Providers\TCAMessenger\TCAMessengerAuth', function ($app) {
            return new TCAMessengerAuth(app('Pubnub\Pubnub'));
        });

        $this->app->bind('Tokenpass\Providers\TCAMessenger\TCAMessengerRoster', function ($app) {
            return new TCAMessengerRoster();
        });

        $this->app->bind('Pubnub\Pubnub', function ($app) {
            return new Pubnub([
                'subscribe_key' => env('PUBNUB_SUBSCRIBE_KEY', 'none'),
                'publish_key'   => env('PUBNUB_PUBLISH_KEY', 'none'),
                'secret_key'    => env('PUBNUB_ADMIN_SECRET_KEY', 'none'),
            ]);
        });

    }


}