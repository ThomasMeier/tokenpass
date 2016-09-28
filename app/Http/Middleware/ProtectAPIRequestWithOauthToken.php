<?php

namespace Tokenpass\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\OAuth\Facade\OAuthGuard;

class ProtectAPIRequestWithOauthToken {


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
            $input = $request->input();
            $oauth_access_token = isset($input['oauth_token']) ? $input['oauth_token'] : null;

            // find user by access token
            list($guard_error_id, $guard_error_description) = OAuthGuard::applyUserByOauthAccessToken($oauth_access_token);

            // require a user
            $user = OAuthGuard::user();
            if ($user AND $user instanceof Authenticatable) {
                $authenticated = true;

                // check scopes
                $required_scopes = array_slice(func_get_args(), 2);
                if ($required_scopes) {
                    foreach($required_scopes as $required_scope) {
                        if (!OAuthGuard::hasScope($required_scope)) {
                            $authenticated = false;

                            EventLog::logError('error.oauth.missingScope', ['scope' => $required_scope]);
                            $error_message = 'One or more scopes are not authorized';
                            $error_code = 403;
                            break;
                        }
                    }
                }

                if ($authenticated) {
                    Auth::setUser($user);
                }
            } else {
                // user not found or invalid
                EventLog::logError('oauthError.'.$guard_error_id, $guard_error_description);
                $error_message = $guard_error_description;
                $error_code = 403;
            }

        } catch (Exception $e) {
            // something else went wrong
            EventLog::logError('error.oauth.unexpected', $e);
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
