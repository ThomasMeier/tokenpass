<?php
namespace Tokenpass\Http\Controllers\API;
use DB, Exception, Response, Input, Hash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;
use Tokenly\TCA\Access;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenpass\Repositories\UserRepository;
use Tokenpass\Util\BitcoinUtil;

class APITCAController extends Controller
{

    use DispatchesJobs;

    public function __construct(OAuthClientRepository $oauth_client_repository, ClientConnectionRepository $client_connection_repository, UserRepository $user_repository)
    {
        $this->oauth_client_repository      = $oauth_client_repository;
        $this->client_connection_repository = $client_connection_repository;  
        $this->user_repository = $user_repository;
    }


    public function checkTokenAccess($username) {
        $input = Input::all();
        $output = array();
        $http_code = 200;

        $include_provisional = true;
        if(isset($input['no_provisional']) AND !$input['no_provisional']){
            $include_provisional = false;
        }
        
        $user_model = User::where('username', $username)->orWhere('slug', $username)->first();
        if(!$user_model){

            $http_code = 404;
            $output['result'] = false;
            $output['error'] = 'Username not found';

        } else {
            
            
            $ops = array();
            $stack_ops = array();
            $checks = array();
            $tca = new Access;
            foreach($input as $k => $v){
                $exp_k = explode('_', $k);
                $k2 = 0;
                if(isset($exp_k[1])){
                    $k2 = intval($exp_k[1]);
                }
                if($exp_k[0] == 'op'){
                    $ops[$k2] = $v;
                }
                elseif($exp_k[0] == 'stackop'){
                    $stack_ops[$k2] = strtoupper($v);
                }
                else{
                    $checks[] = array('asset' => strtoupper($k), 'amount' => round(floatval($v) * 100000000)); //convert amount to satoshis
                }
            }
            $full_stack = array();
            foreach($checks as $k => $row){
                $stack_item = $row;
                if(isset($ops[$k])){
                    $stack_item['op'] = $ops[$k];
                }
                else{
                    $stack_item['op'] = '>='; //default to greater or equal than
                }
                if(isset($stack_ops[$k])){
                    $stack_item['stackOp'] = $stack_ops[$k];
                }
                else{
                    $stack_item['stackOp'] = 'AND';
                }
                $full_stack[] = $stack_item;
            }
            $balances = Address::getAllUserBalances($user_model->id, true, $include_provisional, true);
            $output['result'] = $tca->checkAccess($full_stack, $balances);
        }

        return Response::json($output, $http_code);
    }

    public function checkAddressTokenAccess($address) {
        $input = Input::all();
        $output = array();
        
        $address_is_valid = BitcoinUtil::isValidBitcoinAddress($address);
        if(!$address_is_valid){
            $output['error'] = 'Invalid address';
            $output['result'] = false;
            return Response::json($output, 400);
        }   
        
        $tca = new Access(true);
        $ops = array();
        $stack_ops = array();
        $checks = array();
        foreach($input as $k => $v){
            $exp_k = explode('_', $k);
            $k2 = 0;
            if(isset($exp_k[1])){
                $k2 = intval($exp_k[1]);
            }
            if($exp_k[0] == 'op'){
                $ops[$k2] = $v;
            }
            elseif($exp_k[0] == 'stackop'){
                $stack_ops[$k2] = strtoupper($v);
            }
            else{
                $checks[] = array('asset' => strtoupper($k), 'amount' => round(floatval($v) * 100000000)); //convert amount to satoshis
            }
        }
        $full_stack = array();
        foreach($checks as $k => $row){
            $stack_item = $row;
            if(isset($ops[$k])){
                $stack_item['op'] = $ops[$k];
            }
            else{
                $stack_item['op'] = '>='; //default to greater or equal than
            }
            if(isset($stack_ops[$k])){
                $stack_item['stackOp'] = $stack_ops[$k];
            }
            else{
                $stack_item['stackOp'] = 'AND';
            }
            $full_stack[] = $stack_item;
        }
        

        $output['result'] = $tca->checkAccess($full_stack, false, $address);
        
        return Response::json($output);
    }

    
}
