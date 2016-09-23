<?php

namespace Tokenpass\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tokenpass\Models\Address;
use Tokenpass\Models\UserMeta;
use Tokenly\LaravelApiProvider\Contracts\APIPermissionedUserContract;

class SecondFactor
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
        if(!$user){
            return redirect('/auth/login');
        }
        if ($user instanceof APIPermissionedUserContract) {
            $enabled = Address::checkUser2FAEnabled($user);
            if(!$enabled){
                return $next($request);
            }
            $user_meta = UserMeta::getAllDataById($user->id);
            $signed = 'unsigned';
            foreach($user_meta as $row){
                if($row->meta_key == 'sign_auth' AND trim($row->meta_value) != '') {
                    $signed = $row->extra;
                }
            }
            if ($signed == 'signed') {
                return $next($request);
            } else {
                $redirect = urlencode(route($request->route()->getName(), $request->route()->parameters()));
                return redirect(route('auth.sign', ['redirect' => $redirect]));
            }
        }
    }
}
