<?php

namespace Tokenpass\Http\Controllers\Auth;

use Exception;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\User;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;

class ConnectedAppsController extends Controller
{
    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct(ClientConnectionRepository $client_connection_repository)
    {
        $this->client_connection_repository = $client_connection_repository;

        $this->middleware('auth');

    }

    /**
     * Shows all connected apps
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getConnectedApps(Request $request)
    {
        $user = Auth::user();
        $connection_entries = $this->client_connection_repository->buildConnectedClientDetialsForUser($user);

        return view('auth.connected-apps', ['connection_entries' => $connection_entries]);
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getRevokeAppForm($client_uuid_id, Request $request, OAuthClientRepository $oauth_client_repository)
    {
        $user = Auth::user();
        $client = $oauth_client_repository->findByUuid($client_uuid_id);
        if (!$client) {
            throw new HttpResponseException(new Response("Client not found", 404));
        }

        return view('auth.revoke-app-form', ['client' => $client]);
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postRevokeAppForm($client_uuid_id, Request $request, OAuthClientRepository $oauth_client_repository)
    {
        $user = Auth::user();
        $client = $oauth_client_repository->findByUuid($client_uuid_id);
        if (!$client) {
            throw new HttpResponseException(new Response("Client not found", 404));
        }

        $connection_entries = $this->client_connection_repository->disconnectUserFromClient($user, $client);

        return view('auth.revoke-app-complete', ['client' => $client]);
    }

}
