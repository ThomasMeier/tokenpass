<?php

namespace Tokenpass\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Pubnub\Pubnub;
use Tokenly\AssetNameUtils\Validator as AssetValidator;
use Tokenly\BvamApiClient\BVAMClient;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;
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

        // blade
        Blade::directive('formatSatoshis', function ($expression) {
            return "<?php echo \Tokenly\CurrencyLib\CurrencyUtil::satoshisToFormattedString($expression); ?>";
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

        $this->app->bind('Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager', function ($app) {
            return new PseudoAddressManager(app('Tokenpass\Repositories\AddressRepository'));

        });

    }
}
