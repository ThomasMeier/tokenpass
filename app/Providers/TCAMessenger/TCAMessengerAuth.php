<?php

namespace Tokenpass\Providers\TCAMessenger;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pubnub\Pubnub;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\User;

class TCAMessengerAuth
{

    const TTL_FOREVER = 0;

    function __construct(Pubnub $pubnub) {
        $this->pubnub = $pubnub;
        $this->tokenpass_auth_key = env('PUBNUB_TOKENPASS_AUTH_KEY');
    }

    public function authorizeUser(User $user, $read, $write, $channel, $ttl=0) {
        if ($this->userGrantExists($user, $read, $write, $channel, $ttl)) {
            return true;
        }

        DB::transaction(function() use ($read, $write, $channel, $user, $ttl) {
            $this->cacheUserGrant($user, $read, $write, $channel, $ttl);
            $this->grant($read, $write, $channel, $user->getChannelAuthKey(), $ttl);
        });

        return true;
    }

    public function revokeUser(User $user, $channel) {
        DB::transaction(function() use ($user, $channel) {
            $this->forgetUserGrant($user, $channel);
            $this->revoke($channel, $user->getChannelAuthKey());
        });
        return true;
    }

    public function authorizeTokenpass($read, $write, $channel, $ttl=0) {
        if ($this->tokenpassGrantExists($read, $write, $channel, $ttl)) {
            return true;
        }

        DB::transaction(function() use ($read, $write, $channel, $ttl) {
            $this->cacheTokenpassGrant($read, $write, $channel, $ttl);
            $this->grant($read, $write, $channel, $this->requireTokenpassAuthKey(), $ttl);
        });

        return true;
    }

    public function audit($channel = null, $auth_key = null) {
        return $this->processResponse($this->pubnub->audit($channel, $auth_key));
    }

    public function clearAllCaches() {
        DB::transaction(function() {
            DB::table('pubnub_user_access')->delete();
            DB::table('pubnub_tokenpass_access')->delete();
        });
    }

    public function findUserIDsByChannel($channel) {
        return DB::table('pubnub_user_access')->select('user_id')->where('channel', $channel)->get();
    }

    public function userIsAuthorized($user_id, $channel) {
        $record = DB::table('pubnub_user_access')->select('user_id')
            ->where('user_id', $user_id)
            ->where('channel', $channel)
            ->get();
        return $record->count() ? true : false;
    }

    // ------------------------------------------------------------------------

    // only public for mocking - don't call directly
    public function grant($read, $write, $channel, $auth_key, $ttl) {
        EventLog::debug('pubnub.grant', ['read'=> $read, 'write'=> $write, 'channel'=> $channel, 'auth_key'=> $auth_key, 'ttl'=> $ttl]);
        return $this->processResponse($this->pubnub->grant($read, $write, $channel, $auth_key, $ttl));
    }

    // only public for mocking - don't call directly
    public function revoke($channel, $auth_key) {
        EventLog::debug('pubnub.revoke', ['channel'=> $channel, 'auth_key'=> $auth_key]);
        return $this->processResponse($this->pubnub->revoke($channel, $auth_key));
    }


    // ------------------------------------------------------------------------
    
    protected function processResponse($response) {
        if ($this->isErrorResponse($response)) {
            throw new Exception($response['message'], 1);
        }
        return $response['payload'];
    }

    protected function isErrorResponse($response) {
        return (isset($response['error']) AND $response['error']);
    }

    protected function requireTokenpassAuthKey() {
        if (!$this->tokenpass_auth_key) { throw new Exception("Undefined tokenpass auth key", 1); }
        return $this->tokenpass_auth_key;
    }

    public function userGrantExists(User $user, $read, $write, $channel, $ttl) {
        return $this->getUserGrant($user, $read, $write, $channel, $ttl) ? true : false;
    }

    protected function tokenpassGrantExists($read, $write, $channel, $ttl) {
        return $this->getTokenpassGrant($read, $write, $channel, $ttl) ? true : false;
    }

    // ------------------------------------------------------------------------
    
    protected function getUserGrant(User $user, $read, $write, $channel, $ttl) {
        $results = DB::table('pubnub_user_access')->where([
            ['user_id', '=', $user['id']],
            ['channel', '=', $channel],
            ['read',    '=', $read],
            ['write',   '=', $write],
            ['ttl',     '=', $ttl],
        ])->get();

        return (count($results) > 0 ? $results[0] : false);
    }

    protected function cacheUserGrant(User $user, $read, $write, $channel, $ttl) {
        // clear all previous grants for this user and channel
        $this->forgetUserGrant($user, $channel);

        // add new grant
        DB::table('pubnub_user_access')->insert([
            'user_id' => $user['id'],
            'channel' => $channel,
            'read'    => $read,
            'write'   => $write,
            'ttl'     => $ttl,
            'updated_at' => time(),
        ]);
    }

    protected function forgetUserGrant(User $user, $channel) {
        DB::table('pubnub_user_access')->where([
            ['user_id', '=', $user['id']],
            ['channel', '=', $channel],
        ])->delete();
    }

    protected function getTokenpassGrant($read, $write, $channel, $ttl) {
        $results = DB::table('pubnub_tokenpass_access')->where([
            ['channel', '=', $channel],
            ['read',    '=', $read],
            ['write',   '=', $write],
            ['ttl',     '=', $ttl],
        ])->get();

        return (count($results) > 0 ? $results[0] : false);
    }

    protected function cacheTokenpassGrant($read, $write, $channel, $ttl) {
        // clear all previous grants for this user and channel
        $this->forgetTokenpassGrant($channel);

        // add new grant
        DB::table('pubnub_tokenpass_access')->insert([
            'channel' => $channel,
            'read'    => $read,
            'write'   => $write,
            'ttl'     => $ttl,
            'updated_at' => time(),
        ]);
    }

    protected function forgetTokenpassGrant($channel) {
        DB::table('pubnub_tokenpass_access')->where([
            'channel' => $channel,
        ])->delete();
    }

}
