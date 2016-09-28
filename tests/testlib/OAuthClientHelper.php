<?php

use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\User;

/*
* OAuthClientHelper
*/
class OAuthClientHelper
{
    static $OFFSET = 0;

    public function __construct() {
    }

    public function createConnectedOAuthClientWithTCAScopes(User $user=null, $override_vars=[]) {
        if ($user === null) { $user = app('UserHelper')->getOrCreateSampleUser(); }

        // create
        $oauth_client = $this->createRandomOAuthClient();

        // connect
        $oauth_connection = $this->connectClient($user, $oauth_client);

        // apply scopes
        $this->assignScopes($oauth_connection);

        return $oauth_client;
    }

    public function connectUserSession(User $user, OAuthClient $oauth_client, $scope_ids=null, $options=null) {
        // create a session
        $session_id = DB::table('oauth_sessions')->insertGetId([
            'owner_type' => 'user',
            'owner_id'   => $user['id'],
            'client_id'  => $oauth_client['id'],
        ]);

        // generate a token
        $token = 'TOKEN'.(sprintf('%03d', ++self::$OFFSET));
        $expire_ttl = ($options AND isset($options['expire_time_ttl'])) ? $options['expire_time_ttl'] : 99999;
        DB::table('oauth_access_tokens')->insert([
            'id'          => $token,
            'session_id'  => $session_id,
            'expire_time' => time() + $expire_ttl,
        ]);

        // associate the scopes
        if ($scope_ids === null) { $scope_ids = collect($this->scopeSpecs())->pluck('id')->toArray(); }
        foreach($scope_ids as $scope_id) {
            DB::table('oauth_session_scopes')->insert([
                'session_id' => $session_id,
                'scope_id'   => $scope_id,
            ]);
        }

        return $token;
    }

    public function createRandomOAuthClient($override_vars=[]) {
        return $this->createSampleOAuthClient($this->getRandomOAuthClientVars());
    }

    public function getRandomOAuthClientVars() {
        ++self::$OFFSET;
        return [
            'id'   => 'APITOKEN_'.sprintf('%03d', self::$OFFSET),
            'name' => 'client '.sprintf('%03d', self::$OFFSET),
        ];
    }

    public function createSampleOAuthClient($override_vars=[]) {
        // create an oauth client
        $oauth_client = app('Tokenpass\Repositories\OAuthClientRepository')->create(array_merge([
            'id'     => 'MY_API_TOKEN',
            'secret' => 'MY_SECRET',
            'name'   => 'client one',
        ], $override_vars));

        return $oauth_client;
    }

    public function connectClient(User $user=null, OAuthClient $oauth_client=null) {
        // create an oauth client
        if ($oauth_client === null) { $oauth_client = $this->createSampleOAuthClient(); }
        $oauth_client_id = $oauth_client['id'];


        // create a connection between the user and the oauth client
        $connection_uuid = Uuid::uuid4()->toString();
        DB::table('client_connections')->insert([
            'uuid'       => $connection_uuid,
            'user_id'    => $user['id'],
            'client_id'  => $oauth_client_id,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $oauth_connection = (array)DB::table('client_connections')->where('uuid', $connection_uuid)->first();

        return $oauth_connection;
    }


    public function assignScopes($oauth_connection) {
        // assign the scopes to the connection
        $scope_models = $this->ensureScopes();
        foreach($scope_models as $scope_model) {
            DB::table('client_connection_scopes')->insert([
                'connection_id' => $oauth_connection['id'],
                'scope_id'      => $scope_model['uuid'],
            ]);
        }
    }

    public function ensureScopes() {
        $scope_specs = $this->scopeSpecs();

        $scope_repo = app('Tokenpass\Repositories\OAuthScopeRepository');
        $scope_models = [];
        foreach($scope_specs as $scope_spec) {
            $scope_model = $scope_repo->findByID($scope_spec['id']);
            if (!$scope_model) {
                $scope_model = $scope_repo->create($scope_spec);
            }

            $scope_models[$scope_spec['id']] = $scope_model;
        }

        return $scope_models;
    }

    public function scopeSpecs() {
        return [
            [
                'id'          => 'tca',
                'description' => 'TCA Access',
            ],
            [
                'id'          => 'private-address',
                'description' => 'Private Address',
            ],
            [
                'id'          => 'manage-address',
                'description' => 'Manage Addresses',
            ],
        ];
    }

}
