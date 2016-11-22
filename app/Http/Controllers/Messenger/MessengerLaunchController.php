<?php

namespace Tokenpass\Http\Controllers\Messenger;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Util\SecureKey;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\User;

class MessengerLaunchController extends Controller
{

    public function launch() {
        $user = Auth::user();

        $messenger_oauth_token = $this->issueMessengerOauthToken($user);
        if (!$messenger_oauth_token) {
            return response("Error launching messenger", 500);
        }

        return redirect(env('MESSENGER_URL').'?c='.$user->getChannelName().'&t='.$messenger_oauth_token.'username='.$user['username']);
    }

    protected function issueMessengerOauthToken(User $user) {
        Log::debug("issueMessengerOauthToken");

        $oauth_client = app('Tokenpass\Repositories\OAuthClientRepository')->findById(env('MESSENGER_CLIENT_ID'));
        if (!$oauth_client) {
            Log::error("Oauth client not found for id ".env('MESSENGER_CLIENT_ID'));
            return null;
        }

        $oauth_connection = $this->connectClient($user, $oauth_client);
        if (!$oauth_connection) {
            Log::error("oauth connection not found");
            return null;
        }

        $token = $this->connectUserSession($user, $oauth_client, ['tca']);
        return $token;
    }

    protected function connectClient(User $user, OAuthClient $oauth_client) {
        $client_connection_repository = app('Tokenpass\Repositories\ClientConnectionRepository');

        // find existing connection
        $oauth_connection = $client_connection_repository->findClientConnection($user, $oauth_client);

        // create a connection between the user and the oauth client
        if (!$oauth_connection) {
            $oauth_connection = $client_connection_repository->connectUserToClient($user, $oauth_client, ['tca']);
        }

        return $oauth_connection;
    }

    public function connectUserSession(User $user, OAuthClient $oauth_client, $scope_ids) {
        // create a session
        $session_id = DB::table('oauth_sessions')->insertGetId([
            'owner_type' => 'user',
            'owner_id'   => $user['id'],
            'client_id'  => $oauth_client['id'],
        ]);

        // generate a token
        $token = SecureKey::generate();

        $expire_ttl = 86400; // 1 day
        DB::table('oauth_access_tokens')->insert([
            'id'          => $token,
            'session_id'  => $session_id,
            'expire_time' => time() + $expire_ttl,
        ]);

        // associate the scopes
        foreach($scope_ids as $scope_id) {
            DB::table('oauth_session_scopes')->insert([
                'session_id' => $session_id,
                'scope_id'   => $scope_id,
            ]);
        }

        return $token;
    }
}
