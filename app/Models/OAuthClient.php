<?php

namespace Tokenpass\Models;

use Exception, Session;
use Tokenly\LaravelApiProvider\Model\APIModel, DB;

class OAuthClient extends APIModel {

    protected $table = 'oauth_clients';

    public $incrementing = false;

    protected $api_attributes = ['id','name'];

    public static function getOAuthClientDetailsFromURL($url)
    {
        $client_id = self::getOAUthClientIDFromURL($url);
        if(!$client_id){
            return false;
        }
        $result = DB::table('oauth_clients')->where('id', $client_id)->first();
        return (Array) $result;
    }
    
    public static function getOAuthClientDetailsFromIntended()
    {
        $client_id = self::getOAuthClientIDFromIntended();
        if(!$client_id){
            return false;
        }
        $result = DB::table('oauth_clients')->where('id', $client_id)->first();
        return (Array) $result;        
    }
    
    public static function getOAuthClientIDFromURL($url)
    {
        $parts = parse_url($url);
        if(!isset($parts['query'])){
            return false;
        }
        parse_str($parts['query'], $query);
        if(!isset($query['client_id'])){
            return false;
        }
        $client_id =  $query['client_id'];
        return $client_id;
    }
    
    public static function getOAuthClientIDFromIntended()
    {
        $intended = Session::get('url.intended');
        if(!$intended OR $intended == null){
            return false;
        }
        $client_id = OAuthClient::getOAuthClientIDFromURL($intended);
        return $client_id;
    }

    public static function getUserClients($user_id)
    {
		$get = OAuthClient::where('user_id', $user_id)->get();
		if(count($get) == 0){
			return false;
		}
		return $get;
	}
	
	public static function connectionHasScope($connect_id, $scope)
	{
		$get_scope = OAuthScope::find($scope);
		if(!$get_scope){
			throw new \Exception($scope.' scope not found in system');
		}		
		$scope_connect = DB::table('client_connection_scopes')->where('connection_id', $connect_id)->where('scope_id', $get_scope->uuid)->get();
		if(!$scope_connect OR count($scope_connect) == 0){
			return false;
		}
		return true;
	}
    
    public function countConnections()
    {
        return DB::table('client_connections')->where('client_id', $this->id)->count();
    }
    
    public function connections()
    {
        return DB::table('client_connections')->where('client_id', $this->id)->get();
    }
    
    public function user()
    {
        return User::find($this->user_id);
    }
    
    public function endpoints()
    {
        return DB::table('oauth_client_endpoints')->where('client_id', $this->id)->get();
    }
    
    public function endpointsText()
    {
        $get = $this->endpoints();
        $text = '';
        if($get){
            foreach($get as $row){
                $text .= $row->redirect_uri.PHP_EOL;
            }
        }
        return $text;
    }
	

}
