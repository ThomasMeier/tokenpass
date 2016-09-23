<?php

namespace Tokenpass\Http\Controllers\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Models\OAuthScope;

/**
 */
class OAuthController extends Controller
{
    protected $authorizer;

    public function __construct(OAuthClientRepository $oauth_client_repository, ClientConnectionRepository $client_connection_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;

        // $this->beforeFilter('auth', ['only' => ['getAuthorize', 'postAuthorize']]);

        // $this->middleware('oauth2.error');
        // $this->middleware('oauth2.check-authorization-params', ['only' => ['getAuthorize', 'postAuthorize']]);

        // $this->beforeFilter('oauth', ['only' => ['getUser']]);

    }

    /**
     * Issue the Access Token
     * 
     * @return Response
     */
    public function postAccessToken()
    {
        try {
            return Response::json(Authorizer::issueAccessToken());
        } catch (\Exception $e) {
            Log::error("Exception: ".get_class($e).' '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Show the authorization form
     * 
     * @return Response
     */
    public function getAuthorizeForm()
    {
        $user = Auth::user();

        $authParams = Authorizer::getAuthCodeRequestParams();
        $client_id = $authParams['client']->getId();

        // see if this client is already authorized
        $client = $this->oauth_client_repository->findById($client_id);
        if (!$client) { throw new Exception("Unable to find oauth client for client ".json_encode($client_id, 192)); }
        $already_connected = $this->client_connection_repository->isUserConnectedToClient($user, $client);
        if ($already_connected) {
            // we are already connected.  Issue the code and continue
            $params = $authParams;
            $params['user_id'] = $user->id;
            $redirect_uri = Authorizer::issueAuthCode('user', $params['user_id'], $params);
            return Redirect::to($redirect_uri);
        }

        $formParams = array_except($authParams,'client');
        $formParams['client_id'] = $client_id;
        $formParams['scopes'] = array();
        $scope_list = array();
        foreach($authParams['scopes'] as $scope_k => $scope){
			$formParams['scopes'][] = $scope_k;
            $scope_list[] = OAuthScope::find($scope->getID());
		}
		$formParams['scopes'] = join(',', $formParams['scopes']);

        return View::make('oauth.authorization-form', ['params'=>$formParams, 'client'=>$authParams['client'], 'scopes'=>$scope_list]);
    }

    /**
     * Process the authorization form
     * 
     * @return Response
     */
    public function postAuthorizeForm()
    {
        $user = Auth::user();
        $params = Authorizer::getAuthCodeRequestParams();

        $client_id = $params['client']->getId();
        $params['user_id'] = $user->id;
        $redirect_uri = '';
        $scope_param = Input::get('scopes');
        $scopes = array();
        if(isset($params['scopes'])){
			$scopes = $params['scopes'];
		}
		if($scope_param AND count($scopes) == 0){
			$scopes = explode(',', $scope_param);
		}

        // if the user has allowed the client to access its data, redirect back to the client with an auth code
        if (Input::get('approve') !== null) {
            $redirect_uri = Authorizer::issueAuthCode('user', $params['user_id'], $params);

            // remember this authorization for later
            $client = $this->oauth_client_repository->findById($client_id);
            if (!$client) {
                throw new Exception("Unable to find oauth client for client ".json_encode($client_id, 192));
            }
            if (!$this->client_connection_repository->isUserConnectedToClient($user, $client)) {
                $this->client_connection_repository->connectUserToClient($user, $client, $scopes);
            }
        }

        // if the user has denied the client to access its data, redirect back to the client with an error message
        if (Input::get('deny') !== null) {
            $redirect_uri = Authorizer::authCodeRequestDeniedRedirectUri();
        }

        return Redirect::to($redirect_uri);
    }

    /**
     * get the user
     * @GET("oauth/user", as="oauth.user")
     * @return Response
     */
    public function getUser(UserRepository $user_repository) {
        $owner_id = Authorizer::getResourceOwnerId();

        $user = $user_repository->findById($owner_id);
        Log::info('getUser returning '.json_encode([
            'id'                 => $user['uuid'],
            'name'               => $user['name'],
            'username'           => $user['username'],
            'email'              => $user['email'],
            'email_is_confirmed' => $user->emailIsConfirmed(),
        ], 192));

        return [
            'id'                 => $user['uuid'],
            'name'               => $user['name'],
            'username'           => $user['username'],
            'email'              => $user['email'],
            'email_is_confirmed' => $user->emailIsConfirmed(),
        ];
    }
}
