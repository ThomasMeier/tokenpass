<?php

namespace Tokenpass\Http\Controllers\Accounts;

use Illuminate\Support\Facades\Auth;
use Tokenpass\Http\Controllers\Controller;

class DashboardController extends Controller
{

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }


    public function getDashboard() {
        return view('accounts/dashboard', [
            'user' => Auth::user(),
        ]);
    }

}
