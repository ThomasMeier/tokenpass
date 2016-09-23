<?php

namespace Tokenpass\Http\Controllers\Auth;

use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Facades\Auth;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Repositories\UserRepository;

class PasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    protected $redirectTo = '/dashboard';

    /**
     * Create a new password controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $user_repository)
    {
        $this->user_repository = $user_repository;

        $this->middleware('guest');
    }



    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        // update the user (the repository will hash the password)
        $this->user_repository->update($user, ['password' => $password]);

        // login the user
        Auth::login($user);
    }

}
