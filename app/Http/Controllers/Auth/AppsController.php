<?php
namespace Tokenpass\Http\Controllers\Auth;

use Exception, Input, Session, DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Repositories\OAuthClientRepository;

class AppsController extends Controller
{

    public function __construct(OAuthClientRepository $repository)
    {
		$this->repository = $repository;
        $this->middleware('auth');
		$this->user = Auth::user();
    }
    
    public function index()
    {
		$clients = OAuthClient::getUserClients($this->user->id);
		if($clients){
			foreach($clients as &$client){
				$client->endpoints = $this->loadEndpoints($client);
				$client->user_count = DB::table('client_connections')->where('client_id', $client->id)->count();
			}
		}

		return view('auth.client-apps', array(
			'client_apps' => $clients,
		));
		
	}
    
    public function registerApp()
    {
		$input = Input::all();
		
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
		$client->user_id = $this->user->id;
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
        
        $client = OAuthClient::where('id', $app_id)->first();
        if(!$client OR $client->user_id != $this->user->id){
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

		if(!$client OR $client->user_id != $this->user->id){
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
		$get = OAuthClient::where('id', $app_id)->first();
		if(!$get OR $get->user_id != $this->user->id){
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


        return redirect(route('inventory.pockets'));
    }
    
}
