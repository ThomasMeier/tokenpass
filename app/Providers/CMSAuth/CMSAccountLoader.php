<?php

namespace Tokenpass\Providers\CMSAuth;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tokenpass\Providers\CMSAuth\CMSException;
use Tokenpass\Providers\CMSAuth\Util;
use GuzzleHttp\Psr7\Request;

class CMSAccountLoader {

    protected $cms_accounts_url;
    protected $enable_account_lookups;
    protected $base_path = '/api/v1';

    function __construct($cms_accounts_url, $enable_account_lookups=true) {
        $this->cms_accounts_url       = $cms_accounts_url;
        $this->enable_account_lookups = $enable_account_lookups;
    }

    public function getFullUserInfoWithLogin($username, $password) {
        try {
            $authorize_results = $this->fetchFromAPI('POST', '/auth', ['username' => $username, 'password' => $password, 'force_native' => true]);
        } catch (CMSException $e) {
            Log::debug("Failed to login {$username}:\n".json_encode($e->getJSONResponse(), 192));
            if ($e->getCode() == 401) {
                // unauthorized
                return false;
            }
            throw $e;
        }
        
        if (isset($authorize_results['result'])) {
            return $authorize_results['result'];
        }

        Log::error("Unexpected results from auth: ".json_encode($authorize_results, 192));
        
        throw new Exception("Unexpected results", 1);
    }

    public function usernameExists($username) {
        $username_slug = Util::slugify($username);

        $usernames_map = Cache::get('cms.usernames');

        if ($usernames_map) {
            Log::debug("\$usernames_map contains ".count($usernames_map)." usernames");
            if (isset($usernames_map[$username_slug])) {
                return true;
            }
        }

        // make sure to catch private or uncached usernames
        try {
            $results = $this->fetchFromAPI('GET', '/users/'.$username_slug);
        } catch (CMSException $e) {
            if ($e->getCode() == 400 OR $e->getCode() == 404) {
                return false;
            }
            throw $e;
        }

        if ($results AND isset($results['profile'])) {
            return true;
        }

        return false;
    }

    public function populateUsernamesCache() {
        $usernames_map = [];
        $public_users = $this->getAllPublicUsers();
        foreach($public_users as $details) {
            // save the slug version of the username
            $usernames_map[Util::slugify($details['username'])] = true;
        }

        Cache::forever('cms.usernames', $usernames_map);

        return;
    }

    public function getAllPublicUsers($limit=99999) {
        $results = $this->fetchFromAPI('GET', '/users', ['limit' => $limit]);
        return $results['users'];
    }
    
    public function getUserCoinAddresses($cms_user)
    {
		$results = $this->fetchFromAPI('GET', 'address/get', ['x-auth' => $cms_user['auth']]);
		if($results AND isset($results['addresses'])){
			return $results['addresses'];
		}
		return false;
	}
	
	public function checkTokenAccess($username, $params = array())
	{
		$result = $this->fetchFromAPI('GET', 'tca/check/'.$username, $params);
		if($result AND isset($result['result'])){
			return $result['result'];
		}
		return false;
	}

    protected function fetchFromAPI($method, $path, $parameters=[]) {
        $api_path = $this->base_path.'/'.ltrim($path, '/');
		$client = new GuzzleClient(array('base_uri', $this->cms_accounts_url));
		$request = new Request($method, $this->cms_accounts_url.$api_path);
        if ($method == 'GET') {
            $data = ['query' => $parameters];

        } else {
            $data = ['form_params' => $parameters];
        }
		$method = strtolower($method);
        // send request
        try {
			$response = $client->send($request, $data);
        } catch (Exception $e) {
            if ($response = $e->getResponse()) {
                // interpret the response and error message
                $code = $response->getStatusCode();
                try {
                    $json = json_decode($response->getBody(), true);
                } catch (Exception $parse_json_exception) {
                    // could not parse json
                    $json = null;
                }

                if ($json and isset($json['error'])) {
                    $auth_exception = new CMSException($json['error'], $code);
                    $auth_exception->setJSONResponse($json);
                    throw $auth_exception;
                }
            }

            // if no response, then just throw the original exception
            throw $e;
        }
        $json = json_decode($response->getBody(), true);
        if (!is_array($json)) { throw new Exception("Unexpected response", 1); }

        if ($json and isset($json['error'])) {
            $auth_exception = new CMSException($json['error'], $response->getStatusCode());
            $auth_exception->setJSONResponse($json);
            throw $auth_exception;
        }

        return $json;
    }
}
