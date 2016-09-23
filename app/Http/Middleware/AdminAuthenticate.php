<?php

namespace Tokenpass\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Contracts\APIPermissionedUserContract;

class AdminAuthenticate
{
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
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
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
        $user = $this->auth->user();
        if ($user instanceof APIPermissionedUserContract) {
            if ($user->hasPermission('admin')) {
                return $next($request);
            }
        }

        Log::warning("Admin permissions not found.");
        return response('Unauthorized.', 403);
    }
}
