<?php

namespace Tokenpass\Repositories;

use Exception;
use Illuminate\Support\Facades\Log;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\OAuthScope;
use Tokenpass\Models\User;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use DB;

/*
* ClientConnectionRepository
*/
class ClientConnectionRepository extends APIRepository
{

    protected $model_type = 'Tokenpass\Models\ClientConnection';


    public function connectUserToClient(User $user, OAuthClient $client, $scopes = array()) {
        $create = $this->create([
            'user_id'   => $user['id'],
            'client_id' => $client['id'],
        ]);
       if(count($scopes) > 0){
			foreach($scopes as $scope){
				if(!is_string($scope)){
					if(method_exists($scope, 'getId')){
						$scope = $scope->getId();
					}
					else{
						$scope = $scope->id;
					}
				}
				$getScope = OAuthScope::find($scope);
				if($getScope){
					$id = $create->id;
					DB::table('client_connection_scopes')->insert(array('connection_id' => $id, 'scope_id' => $getScope->uuid));
				}
			}
		}

        return $create;
    }

    public function disconnectUserFromClient(User $user, OAuthClient $client) {
        $client_connection = $this->findClientConnection($user, $client);

        if ($client_connection) {
            $this->delete($client_connection);
        }
    }

    public function isUserConnectedToClient(User $user, OAuthClient $client) {
        $client_connection = $this->findClientConnection($user, $client);
        return ($client_connection ? true : false);
    }

    public function findClientConnection(User $user, OAuthClient $client) {
        return $this->prototype_model
            ->where('user_id', $user['id'])
            ->where('client_id', $client['id'])
            ->first();
    }

    public function buildConnectedClientDetialsForUser(User $user) {
        $client_repository = app('Tokenpass\Repositories\OAuthClientRepository');

        $out = [];
        foreach ($this->prototype_model->where('user_id', $user['id'])->get() as $client_connection) {
            $out[] = [
                'connection' => $client_connection,
                'client'     => $client_repository->findByID($client_connection['client_id']),
                'scopes' => $client_connection->scopes(),
            ];
        }
        return $out;
    }
    
    public function findByClientId($client_id)
    {
        return $this->prototype_model->where('client_id', $client_id)->get();
    }
    
    public function getConnectionScopes($connection_id)
    {
        $get = $this->prototype_model->where('id', $connection_id)->first();
        if($get){
            return $get->scopes();
        }
        return array();
    }

}
