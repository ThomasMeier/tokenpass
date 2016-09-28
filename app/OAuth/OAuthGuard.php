<?php

namespace Tokenpass\OAuth;


use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Repositories\UserRepository;

class OAuthGuard {

    protected $access_token;
    protected $oauth_session;
    protected $user;
    protected $scopes;

    function __construct(UserRepository $user_repository) {
        $this->user_repository = $user_repository;
        $this->clearAuth();
    }

    public function applyUserByOauthAccessToken($token) {
        $this->clearAuth();

        if (!strlen($token)) {
            return ['accessTokenNotProvided', "No access token provided"];
        }

        // get the token
        $access_token = (array)DB::table('oauth_access_tokens')->where('id', $token)->first();
        if (!$access_token) {
            return ['accessTokenNotFound', "This access token was not found"];
        }

        // check access token expiration
        if ($access_token['expire_time'] <= time()) {
            Log::debug("access token expired");
            return ['accessTokenExpired', "This access token has expired"];
        }
        
        // get the session
        $oauth_session = (array)DB::table('oauth_sessions')->where('id', $access_token['session_id'])->first();
        if (!$oauth_session OR $oauth_session['owner_type'] != 'user') {
            return ['ownerTypeInvalid', "Invalid owner type"];
        }

        // load the user
        $user = $this->user_repository->findByID($oauth_session['owner_id']);
        if (!$user) {
            return ['userNotFound', "User not found"];
        }

        $this->setAccessToken($access_token);
        $this->setOAuthSession($oauth_session);
        $this->setOAuthUser($user);

        return [null, null];
    }

    public function user() {
        return $this->user;
    }

    public function session() {
        return $this->oauth_session;
    }

    public function accessToken() {
        return $this->access_token;
    }

    public function hasScope($scope_id) {
        if ($this->scopes === null) { $this->loadScopes(); }

        return isset($this->scopes[$scope_id]);
    }

    // ------------------------------------------------------------------------

    protected function clearAuth() {
        $this->access_token  = null;
        $this->oauth_session = null;
        $this->user          = null;
        $this->scopes        = null;
    }

    protected function setAccessToken($access_token) {
        $this->access_token = $access_token;
    }

    protected function setOAuthSession($oauth_session) {
        $this->oauth_session = $oauth_session;
    }

    protected function setOAuthUser($user) {
        $this->user = $user;
    }

    protected function loadScopes() {
        $this->scopes = [];

        if (!$this->oauth_session) { return; }

        $scopes = DB::table('oauth_sessions')
            ->select('oauth_scopes.*')
            ->join('oauth_session_scopes', 'oauth_sessions.id', '=', 'oauth_session_scopes.session_id')
            ->join('oauth_scopes', 'oauth_scopes.id', '=', 'oauth_session_scopes.scope_id')
            ->where('oauth_sessions.id', $this->oauth_session['id'])
            ->get();

        foreach ($scopes as $scope) {
            $scope = (array)$scope;
            $this->scopes[$scope['id']] = $scope;
        }
    }
}
