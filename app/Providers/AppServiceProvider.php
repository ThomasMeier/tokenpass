<?php

namespace Tokenpass\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Pubnub\Pubnub;
use Tokenly\AssetNameUtils\Validator as AssetValidator;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Providers\TCAMessenger\TCAMessengerAuth;
use Tokenpass\Util\BitcoinUtil;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', function($view){
            $view->with('user', Auth::user());
        });

        // make all forms use https
        if (env('USE_SSL', false)) {
            URL::forceSchema('https');
        }

        Validator::extend('bitcoin', function($attribute, $value, $parameters, $validator) {
            return BitcoinUtil::isValidBitcoinAddress($value);
        });

        Validator::extend('token', function($attribute, $value, $parameters, $validator) {
            return AssetValidator::isValidAssetName($value);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('oauthguard', function ($app) {
            return app('Tokenpass\OAuth\OAuthGuard');
        });
        $this->app->singleton('oauthclientguard', function ($app) {
            return app('Tokenpass\OAuth\OAuthClientGuard');
        });

        $this->app->bind('Tokenly\BvamApiClient\BVAMClient', function ($app) {
            return new BVAMClient(env('BVAM_URL'));
        });

        $this->app->bind('Tokenpass\Providers\TCAMessenger\TCAMessenger', function ($app) {
            return new TCAMessenger(
                app('Tokenly\BvamApiClient\BVAMClient'),
                app('Tokenpass\Providers\TCAMessenger\TCAMessengerAuth'),
                app('Tokenpass\Providers\TCAMessenger\TCAMessengerActions'),
                app('Pubnub\Pubnub')
            );
        });

        $this->app->bind('Tokenpass\Providers\TCAMessenger\TCAMessengerAuth', function ($app) {
            return new TCAMessengerAuth(app('Pubnub\Pubnub'));
        });

        $this->app->bind('Pubnub\Pubnub', function ($app) {
            return new Pubnub([
                'subscribe_key' => env('PUBNUB_SUBSCRIBE_KEY'),
                'publish_key'   => env('PUBNUB_PUBLISH_KEY'),
                'secret_key'    => env('PUBNUB_ADMIN_SECRET_KEY'),
            ]);
        });

    }
}
