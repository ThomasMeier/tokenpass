<?php

namespace Tokenpass\Http\Controllers\PlatformAdmin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Repositories\UserRepository;

class AssumeUserRoleController extends Controller
{

    public function assumeRole($username, UserRepository $user_repository) {
        $user = Auth::user();
        if (!$user OR !$user->hasPermission('platformAdmin')) {
            return response('Unauthorized.', 403);
        }

        $target_user = $user_repository->findByUsername($username);
        if (!$target_user) {
            return response('Not found', 404);
        }

        // login as user
        Auth::login($target_user);

        return redirect(route('user.dashboard'));
    }

}
