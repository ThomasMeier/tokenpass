<?php

namespace Tokenpass\Http\Controllers\Accounts;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
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
        // if we just registered while on our way to granting an application access
        //   go there now
        $intended_url = Session::pull('url.intended', null);
        if ($intended_url) {
            return redirect($intended_url);
        }

        return view('accounts/dashboard', [
            'user' => Auth::user(),
        ]);
    }

}
