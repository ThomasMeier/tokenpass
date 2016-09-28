<?php

namespace Tokenpass\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Tokenly\HmacAuth\Exception\AuthorizationException;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\OAuth\Facade\OAuthClientGuard;
use Tokenpass\Repositories\OAuthClientRepository;

class AuthenticateClientAPIRequest {

    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(OAuthClientRepository $oauth_client_repository)
    {
        $this->oauth_client_repository = $oauth_client_repository;

        $this->initAuthenticator();
    }

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    protected function initAuthenticator() {
        $this->hmac_validator = new \Tokenly\HmacAuth\Validator(function($api_token) {
            // lookup the API secrect by $api_token using $this->auth
            $oauth_client = $this->oauth_client_repository->findById($api_token);
            if (!$oauth_client) { return null; }

            // populate OAuthGuard with the $oauth_client
            OAuthClientGuard::setOAuthClient($oauth_client);

            // the purpose of this function is to look up the secret
            $api_secret = $oauth_client['secret'];
            return $api_secret;
        });
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $authenticated = false;

        try {
            $authenticated = $this->hmac_validator->validateFromRequest($request);

        } catch (AuthorizationException $e) {
            // unauthorized
            EventLog::logError('error.authclient.unauthenticated', $e, ['remoteIp' => $request->getClientIp()]);
            $error_message = $e->getAuthorizationErrorString();
            $error_code = $e->getCode();

            if (!$error_message) { $error_message = 'Authorization denied.'; }

        } catch (Exception $e) {
            // something else went wrong
            EventLog::logError('error.authclient.unexpected', $e);
            $error_message = 'An unexpected error occurred';
            $error_code = 500;
        }

        if (!$authenticated) {
            $response = new JsonResponse([
                'message' => $error_message,
                'errors' => [$error_message],
            ], $error_code);
            return $response;
        }

        return $next($request);
    }

}
