<?php

namespace Tokenpass\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
    }
}
