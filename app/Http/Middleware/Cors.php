<?php

namespace Tokenpass\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if ($request->method() == 'OPTIONS') {
            // don't process OPTIONS requests
            $response = new Response('', 200);
        } else {
            $response = $next($request);
        }

        // add CORS headers
        $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'origin, accept, content-type, x-tokenly-auth-api-token, x-tokenly-auth-nonce, x-tokenly-auth-signature')
            ;

        return $response;
    }
}
