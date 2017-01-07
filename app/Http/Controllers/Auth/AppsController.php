<?php
namespace Tokenpass\Http\Controllers\Auth;

use Exception, Input, Session, DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\AppCredits;
use Tokenpass\Repositories\OAuthClientRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class AppsController extends Controller
{

    public function __construct(OAuthClientRepository $repository)
    {
		$this->repository = $repository;
        $this->middleware('auth');
    }
    
    public function index()
    {
        $user = Auth::user();
		$clients = OAuthClient::getUserClients($user->id);
		if($clients){
			foreach($clients as &$client){
				$client->endpoints = $this->loadEndpoints($client);
				$client->user_count = DB::table('client_connections')->where('client_id', $client->id)->count();
			}
		}
        
        $credit_groups = AppCredits::where('user_id', $user->id)->get();
        foreach($credit_groups as $k => $group){
            $credit_groups[$k]->num_accounts = $group->numAccounts();
        }

		return view('auth.client-apps', array(
			'client_apps' => $clients,
            'credit_groups' => $credit_groups,
		));
		
	}
    
    public function registerApp()
    {
		$input = Input::all();
		$user = Auth::user();

		if(!isset($input['name']) OR trim($input['name']) == ''){
            return $this->ajaxEnabledErrorResponse('Client name required', route('auth.apps'));
		}
		
		$name = trim(htmlentities($input['name']));
		$endpoints = '';
		if(isset($input['endpoints'])){
			$endpoints = trim($input['endpoints']);
		}

        if(!isset($input['app_link']) OR trim($input['app_link']) == ''){
            return $this->ajaxEnabledErrorResponse('Please add an app URL', route('auth.apps'));
        }

        $app_link = '';
        if(!filter_var($input['app_link'], FILTER_VALIDATE_URL)){
            return $this->ajaxEnabledErrorResponse('Please enter a valid app URL', route('auth.apps'));
        }
        $app_link = $input['app_link'];

		$token_generator = app('Tokenly\TokenGenerator\TokenGenerator');
		$client = new OAuthClient;
		$client->id = $token_generator->generateToken(32, 'I');
		$client->secret = $token_generator->generateToken(40, 'K');
		$client->name = $name;
		$client->uuid = Uuid::uuid4()->toString();
		$client->user_id = $user->id;
        $client->app_link = $app_link;
		$save = $client->save();
		
		if(!$save){
            return $this->ajaxEnabledErrorResponse('Error saving new application', route('auth.apps'));
		}
		
		try{
			$update_endpoints = $this->updateEndpoints($client, $endpoints);
		}
		catch(InvalidArgumentException $e){
            $client->delete();
            return $this->ajaxEnabledErrorResponse('Invalid client endpoints', route('auth.apps'));
		}
		
        return $this->ajaxEnabledSuccessResponse('Client application registered!', route('auth.apps'));
	}

    public function regenerateApp($app_id) {
        $user = Auth::user();

        $client = OAuthClient::where('id', $app_id)->first();
        if(!$client OR $client->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Client application not found', route('auth.apps'));
        }

        $token_generator = app('Tokenly\TokenGenerator\TokenGenerator');

        $client->id = $token_generator->generateToken(32, 'I');
        $client->secret = $token_generator->generateToken(40, 'K');
        $save = $client->save();

        if(!$save){
            return $this->ajaxEnabledErrorResponse('Error saving new application', route('auth.apps'));
        }
        else {
            return $this->ajaxEnabledSuccessResponse('Client application updated.', route('auth.apps'));
        }
    }

	public function updateApp($app_id)
	{
		$client = OAuthClient::where('id', $app_id)->first();
        $user = Auth::user();

		if(!$client OR $client->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Client application not found', route('auth.apps'));
		}	
		else{
			$input = Input::all();
			$name = trim(htmlentities($input['name']));
			$endpoints = '';
			if(isset($input['endpoints'])){
				$endpoints = trim($input['endpoints']);
			}			
            
            $app_link = '';
            if(isset($input['app_link']) AND trim($input['app_link']) != ''){
                if(!filter_var($input['app_link'], FILTER_VALIDATE_URL)){
                    return $this->ajaxEnabledErrorResponse('Please enter a valid app URL', route('auth.apps'));
                }
                $app_link = $input['app_link'];
            }            
			
			$client->name = $name;
            $client->app_link = $app_link;
			$save = $client->save();
			
			if(!$save){
                return $this->ajaxEnabledErrorResponse('Error saving new application', route('auth.apps'));
			}
			else{
				try{
					$update_endpoints = $this->updateEndpoints($client, $endpoints);
				}
				catch(InvalidArgumentException $e){
                    return $this->ajaxEnabledErrorResponse('Invalid client endpoints', route('auth.apps'));
				}
			}
		}

        return $this->ajaxEnabledSuccessResponse('Client application updated.', route('auth.apps'));
	}
	
	public function deleteApp($app_id)
	{
        $user = Auth::user();

		$get = OAuthClient::where('id', $app_id)->first();
		if(!$get OR $get->user_id != $user->id){
			Session::flash('message', 'Client application not found');
			Session::flash('message-class', 'alert-danger');
		}
		else{
			$delete = $get->delete();
			if(!$delete){
				Session::flash('message', 'Error deleting client application');
				Session::flash('message-class', 'alert-danger');
			}
			else{
				Session::flash('message', 'Client application deleted!');
				Session::flash('message-class', 'alert-success');
			}
		}
		
		return redirect('auth/apps');
	}
	
   protected function updateEndpoints(OAuthClient $client, $endpoints_string) {
        $endpoints = [];
        foreach (explode("\n", $endpoints_string) as $endpoint) {
            $endpoint = trim($endpoint);
            if (!strlen($endpoint)) { continue; }

            $url = parse_url($endpoint);
            $scheme = isset($url['scheme']) ? $url['scheme'].'://' : '';
            $host = isset($url['host']) ? $url['host'] : '';
            $port = isset($url['port']) ? ':'.$url['port'] : '';
            $user = isset($url['user']) ? $url['user'] : '';
            $pass = isset($url['pass']) ? ':'.$url['pass']  : '';
            $pass = ($user || $pass) ? "$pass@" : '';
            $path = isset($url['path']) ? $url['path'] : '';
            $query = isset($url['query']) && $url['query'] ? '?'.$url['query'] : '';
            $fragment = isset($url['fragment']) ? '#'.$url['fragment'] : '';

            if (!$host OR !$scheme) { throw new InvalidArgumentException("URL was invalid", 1); }

            $endpoint = $scheme.$user.$pass.$host.$port.$path.$query.$fragment;

            if (strlen($endpoint)) { $endpoints[] = $endpoint; }
        }

        DB::transaction(function() use ($client, $endpoints) {
            // delete all
            DB::table('oauth_client_endpoints')
                ->where('client_id', $client['id'])
                ->delete();

            // add new
            foreach($endpoints as $endpoint) {
                DB::table('oauth_client_endpoints')
                    ->insert([
                        'client_id' => $client['id'],
                        'redirect_uri' => $endpoint,
                    ]);
            }
        });
    }

    protected function loadEndpoints(OAuthClient $client) {
        $out = '';
        foreach (DB::table('oauth_client_endpoints')->where('client_id', $client['id'])->get() as $endpoint) {
            // Log::debug("\$endpoint=".json_encode($endpoint, 192));
            $out .= $endpoint->redirect_uri."\n";
        }

        return trim($out);
    }    
    
    
    
    protected function registerAppCreditGroup()
    {
		$input = Input::all();
		$user = Auth::user();
        
        if(!isset($input['name']) OR trim($input['name']) == ''){
            return $this->ajaxEnabledErrorResponse('App Credit Group name required', route('auth.apps').'#app-credits');
        }
        
        $user_id = $user->id;
        $uuid = Uuid::uuid4()->toString();
        $name = trim($input['name']);
        $app_whitelist = null;
        if(isset($input['app_whitelist']) AND trim($input['app_whitelist']) != ''){
            $exp_list = explode("\n", trim($input['app_whitelist']));
            $client_ids = array();
            foreach($exp_list as $k => $v){
                $client_id = trim($v);
                $find_client = OAuthClient::find($client_id);
                if(!$find_client){
                    return $this->ajaxEnabledErrorResponse('Invalid Client ID '.$client_id.' for App Credit Group', route('auth.apps').'#app-credits');
                }
                $client_ids[] = $client_id;
            }
            $app_whitelist = join("\n", $client_ids);
        }
        $active = true;
        
        $credit_group = new AppCredits;
        $credit_group->user_id = $user_id;
        $credit_group->uuid = $uuid;
        $credit_group->name = $name;
        $credit_group->active = $active;
        $credit_group->app_whitelist = $app_whitelist;
        
        $save = $credit_group->save();
        
        if(!$save){
            return $this->ajaxEnabledErrorResponse('Error creating App Credit Group', route('auth.apps').'#app-credits');
        }
        
        return $this->ajaxEnabledSuccessResponse('App Credit Group created!', route('auth.apps').'#app-credits');
    }
    
    protected function updateAppCreditGroup($uuid)
    {
		$input = Input::all();
		$user = Auth::user();        
        $credit_group = AppCredits::where('uuid', $uuid)->first();
        
        if(!$credit_group OR $credit_group->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Invalid App Credit Group', route('auth.apps').'#app-credits');
        }

        if(!isset($input['name']) OR trim($input['name']) == ''){
            return $this->ajaxEnabledErrorResponse('App Credit Group name required', route('auth.apps').'#app-credits');
        }
        
        $name = trim($input['name']);
        $app_whitelist = null;
        if(isset($input['app_whitelist']) AND trim($input['app_whitelist']) != ''){
            $exp_list = explode("\n", trim($input['app_whitelist']));
            $client_ids = array();
            foreach($exp_list as $k => $v){
                $client_id = trim($v);
                $find_client = OAuthClient::find($client_id);
                if(!$find_client){
                    return $this->ajaxEnabledErrorResponse('Invalid Client ID '.$client_id.' for App Credit Group', route('auth.apps').'#app-credits');
                }
                $client_ids[] = $client_id;
            }
            $app_whitelist = join("\n", $client_ids);
        }

        $credit_group->name = $name;
        $credit_group->app_whitelist = $app_whitelist;
        
        $save = $credit_group->save();
        
        if(!$save){
            return $this->ajaxEnabledErrorResponse('Error updating App Credit Group', route('auth.apps').'#app-credits');
        }
        
        return $this->ajaxEnabledSuccessResponse('App Credit Group updated!', route('auth.apps').'#app-credits');
    }
    
    protected function deleteAppCreditGroup($uuid)
    {
		$user = Auth::user();        
        $credit_group = AppCredits::where('uuid', $uuid)->first();
        
        if(!$credit_group OR $credit_group->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Invalid App Credit Group', route('auth.apps').'#app-credits');
        }
        
        $delete = $credit_group->delete();
        
        if(!$delete){
            return $this->ajaxEnabledErrorResponse('Error deleting App Credit Group', route('auth.apps').'#app-credits');
        }
        
        return $this->ajaxEnabledSuccessResponse('App Credit Group deleted!', route('auth.apps').'#app-credits');        
        
    }
    
    protected function viewAppCreditGroupUsers($uuid)
    {
		$user = Auth::user();        
        $credit_group = AppCredits::where('uuid', $uuid)->first();
        
        if(!$credit_group OR $credit_group->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Invalid App Credit Group', route('auth.apps').'#app-credits');
        }
        
        
        $credit_accounts = $credit_group->getAccounts();
        $num_accounts = $credit_group->numAccounts();
        $credit_balance = $credit_group->balance();
        
		return view('auth.apps.credit-accounts', array(
            'credit_group' => $credit_group,
            'credit_accounts' => $credit_accounts,
            'num_accounts' => $num_accounts,
            'credit_balance' => $credit_balance,
		));
    }
    
    
    protected function viewAppCreditGroupTransactions($uuid, $account_uuid = false)
    {
		$user = Auth::user();        
        $credit_group = AppCredits::where('uuid', $uuid)->first();
        
        if(!$credit_group OR $credit_group->user_id != $user->id){
            return $this->ajaxEnabledErrorResponse('Invalid App Credit Group', route('auth.apps').'#app-credits');
        }
        
        $credit_txs = $credit_group->transactionHistory($account_uuid);
        $num_accounts = $credit_group->numAccounts();
        $credit_balance = $credit_group->balance();
        
        $credit_account = false;
        $page_route = route('app-credits.history', $uuid);
        if($account_uuid){
            $page_route = route('app-credits.history.account', array($uuid, $account_uuod));
            $credit_account = $credit_group->getAccount($account_uuid);
        }
        
        $page = Input::get('page', 1); // Get the current page or default to 1, this is what you miss!
        $perPage = 100;
        $offset = ($page * $perPage) - $perPage;
        
        $items = array_slice($credit_txs, $offset, $perPage, true);
        $paginator =  new LengthAwarePaginator($items, count($credit_txs), $perPage, $page, ['path' => $page_route, 'query' => Input::all()]); 
     
		return view('auth.apps.credit-history', array(
            'credit_group' => $credit_group,
            'credit_txs' => $items,
            'num_accounts' => $num_accounts,
            'credit_balance' => $credit_balance,
            'credit_account' => $credit_account,
            'paginator' => $paginator,
            'tx_count' => count($credit_txs),
            'tx_showing' => count($items),
		));
    }
    
    
    
    
    
    
    
    
    

    // ------------------------------------------------------------------------
    protected function ajaxEnabledErrorResponse($error_message, $redirect_url, $error_code = 400) {
        if (Request::ajax()) {
            return Response::json(['success' => false, 'error' => $error_message], $error_code);
        }

        Session::flash('message', $error_message);
        Session::flash('message-class', 'alert-danger');
        return redirect($redirect_url);
    }

    protected function ajaxEnabledSuccessResponse($success_message, $redirect_url, $http_code = 200) {
        if (Request::ajax()) {
            return Response::json([
                'success'     => true,
                'message'     => $success_message,
                'redirectUrl' => $redirect_url,
            ], $http_code);
        }

        Session::flash('message', $success_message);
        Session::flash('message-class', 'alert-success');


        return redirect($redirect_url);
    }
    
}
