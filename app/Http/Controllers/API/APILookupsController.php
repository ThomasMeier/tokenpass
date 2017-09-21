<?php
namespace Tokenpass\Http\Controllers\API;
use DB, Exception, Response, Input, Hash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tokenpass\Models\OAuthClient as AuthClient;
use Log;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Util\BitcoinUtil;

class APILookupsController extends Controller
{

    use DispatchesJobs;

    public function __construct(OAuthClientRepository $oauth_client_repository, ClientConnectionRepository $client_connection_repository, UserRepository $user_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;  
        $this->user_repository = $user_repository;
    }


    
    public function lookupUserByAddress($address)
    {
        $output = array();
        $output['result'] = false;
        $input = Input::all();

        // lookup single address/user
        $found_address_model = Address::where('address', $address)->where('verified', 1)->first();
        if($found_address_model){
            if($found_address_model->public == 0 OR $found_address_model->active_toggle == 0 OR $found_address_model->verified == 0){
                $found_address_model = false;
            }
        }
        if(!$found_address_model){
            $output['error'] = 'User not found';
            return Response::json($output, 404);
        }
        
        $user = User::find($found_address_model->user_id);
        
        $result = array();
        $result['username'] = $user->username;
        $result['address'] = $found_address_model->address;
        $result['email'] = $user->email;
        $output['result'] = $result;

        return Response::json($output);
    }
    
    public function lookupMultipleUsersByAddresses()
    {
        $output = [];
        $output['result'] = false;
        $input = Input::all();

        if(isset($input['address_list']) AND is_array($input['address_list'])){
            // lookup multiple users at once
            $address_models = Address::select('address', 'user_id', 'public', 'active_toggle', 'verified')->whereIn('address', $input['address_list'])->get();
            if ($address_models) {
                $user_ids = array();
                foreach($address_models as $k => $row){
                    if($row->public == 1 AND $row->verified == 1 AND $row->active_toggle == 1 ){
                        if(!in_array($row->user_id, $user_ids)){
                            $user_ids[] = $row->user_id;
                        }
                    } else {
                        unset($address_models[$k]);
                        continue;
                    }
                }
                $output['users'] = array();
                $user_models = User::select('id', 'username', 'email')->whereIn('id', $user_ids)->get();
                if($user_models){
                    foreach($address_models as $row){
                        foreach($user_models as $user){
                            if($user->id == $row->user_id){
                                $output['users'][$row->address] = 
                                    array('username' => $user->username, 'address' => $row->address,
                                          'email' => $user->email
                                         );
                                continue 2;
                            }
                        }
                    }
                }
                if(count($output['users']) > 0){
                    $output['result'] = true;
                }
            }
        }

        return Response::json($output);
    }
    

    public function lookupAddressByUser($username)
    {
        $output = array();
        $output['result'] = false;
        $input = Input::all();

        $user_model = User::where('username', $username)->orWhere('slug', $username)->first();
        if(!$user_model){
            $output['error'] = 'User not found';
            return Response::json($output, 404);
        }
        
        $result = array();
        $result['username'] = $user_model->username;
        
        $addresses = Address::getAddressList($user_model->id, 1, 1, true);
        $result['address'] = false;
        if($addresses AND count($addresses) > 0){
            $result['address'] = $addresses[0]->address;
        }
        $result['email'] = $user_model->email;
        $output['result'] = $result;
        return Response::json($output);
    }
    
    public function checkUserExists($username)
    {
        $output = array();
        $output['result'] = false;

        $query = User::where('username', $username);

        if (!Input::get('strict')) {
            // loose lookup by default
            $query->orWhere('slug', $username)->orWhere('email', $username);
        }

        $user_model = $query->first();
        if($user_model){
            $output['result'] = true;
            $id_hash = Input::get('id_hash');
            if($id_hash){
                //check against hash of their tokenly_uuid for extra verification
                $real_hash = hash('sha256', $user_model->uuid);
                if($id_hash != $real_hash){
                    $output['result'] = false;
                }
            }            
        }
        return Response::json($output);
    }

    public function lookupUserByEmail($email) {
        $output = array();
        $output['result'] = false;
        $input = Input::all();
        
        $oauthClient = false;
        if(isset($input['client_id'])){
            $oauthClient = AuthClient::find($input['client_id']);
        }
        if(!$oauthClient){
            $output['error'] = 'Invalid API client ID'.json_encode($input);
            return Response::json($output, 400);
        }
        
        //check oauth client privileges
        $privs = $oauthClient->privileges();
        if(!$privs OR !isset($privs['canLookupUsersByEmail']) OR !$privs['canLookupUsersByEmail']){
            $output['error'] = 'Client does not have permission to use this API method';
            return Response::json($output, 400);
        }        
        
        $user_model = User::where('email', $email)->first();
        if(!$user_model){
            $output['error'] = 'User not found';
            return Response::json($output, 404);
        }

        //It doesn't matter if there are no addresses
        $addresses = Address::getAddressList($user_model->id, 1, 1, true);

        $result = array();
        $result['username'] = $user_model->username;
        if (count($addresses) > 0) {
            $result['address'] = $addresses[0]->address;
        }
        $result['email'] = $user_model->email;
        $output['result'] = $result;
        return Response::json($output);
    }
}
