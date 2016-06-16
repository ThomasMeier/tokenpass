<?php

namespace TKAccounts\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use TKAccounts\Models\Address;
use TKAccounts\Models\UserMeta;
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
        if ($user instanceof APIPermissionedUserContract) {
            $user_meta = UserMeta::getAllDataById($user->id);
            $signed = 'unsigned';
            foreach($user_meta as $row){
                if($row->meta_key == 'sign_auth') {
                    $signed = $row->extra;
                }
            }
            if ($signed == 'signed') {
                return $next($request);
            } else {
                return redirect(route('auth.sign'));
            }
        }
    }
}
