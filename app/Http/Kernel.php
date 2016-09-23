<?php

namespace Tokenpass\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Tokenpass\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,

        // handle oAuth exceptions
        \LucaDegasperi\OAuth2Server\Middleware\OAuthExceptionHandlerMiddleware::class,

        // trust configured proxies
        \Fideloper\Proxy\TrustProxies::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth'                       => \Tokenpass\Http\Middleware\Authenticate::class,
        'auth.basic'                 => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest'                      => \Tokenpass\Http\Middleware\RedirectIfAuthenticated::class,

        // must be enabled on a per-route basis
        'csrf'                       => \Tokenpass\Http\Middleware\VerifyCsrfToken::class,

        'oauth'                      => \LucaDegasperi\OAuth2Server\Middleware\OAuthMiddleware::class,
        'oauth-owner'                => \LucaDegasperi\OAuth2Server\Middleware\OAuthOwnerMiddleware::class,
        'check-authorization-params' => \LucaDegasperi\OAuth2Server\Middleware\CheckAuthCodeRequestMiddleware::class,
        'sign'                       => \Tokenpass\Http\Middleware\SecondFactor::class,

        // require admin
        'admin'                      => \Tokenpass\Http\Middleware\AdminAuthenticate::class,

        // TLS
        'tls'                        => \Tokenpass\Http\Middleware\RequireTLS::class,
    ];
}
