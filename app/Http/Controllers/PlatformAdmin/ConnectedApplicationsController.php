<?php

namespace Tokenpass\Http\Controllers\PlatformAdmin;

use Illuminate\Support\Facades\Log;
use Tokenpass\Repositories\ClientConnectionRepository;
use Tokenly\PlatformAdmin\Controllers\ResourceController;
use Input, DB;
use Illuminate\Http\Request;
use Tokenpass\Models\OAuthScope;

class ConnectedApplicationsController extends ResourceController
{

    protected $view_prefix      = 'connectedapps';
    protected $repository_class = ClientConnectionRepository::class;


    public function __construct()
    {
        $this->middleware('sign');

    }
    
    // ------------------------------------------------------------------------

    protected function getValidationRules() {
        return [
            'client_id' => 'required|max:255',
            'user_id'   => 'required|numeric:255',
        ];
    }


    protected function modifyViewData_edit($view_data) {
        return $this->addClientAndUserOptions($view_data);
    }

    protected function modifyViewData_create($view_data) {
        return $this->addClientAndUserOptions($view_data);
    }

    protected function addClientAndUserOptions($view_data) {
        $clients = app('Tokenpass\Repositories\OAuthClientRepository')->findAll();
        $view_data['client_options'] = $clients->pluck('id')->combine($clients->pluck('name'));

        $users = app('Tokenpass\Repositories\UserRepository')->findAll();
        $view_data['user_options'] = $users->pluck('id')->combine($users->pluck('username'));
        
        if(isset($view_data['model']) AND is_object($view_data['model'])){
            $scopes = $view_data['model']->scopes();
            $view_data['scope_ids'] = '';
            if($scopes){
                $scope_ids = array();
                foreach($scopes as $scope){
                    $scope_ids[] = $scope->id;
                }
                $view_data['scope_ids'] = join(', ', $scope_ids);
            }
        }

        return $view_data;
    }
    
    public function index(Request $request)
    {
        
        $client_id = Input::get('client_id');
        if($client_id){
            $models = $models = $this->resourceRepository()->findByClientId($client_id);
        }
        else{
            $models = $this->resourceRepository()->findAll();
        }
        
        return view('platformadmin.'.$this->view_prefix.'.index', $this->modifyViewData([
            'models' => $models,
        ], __FUNCTION__));
    }
    
    public function update($id, Request $request)
    {
        $scope_ids = explode(',',Input::get('scope_ids'));
        $scope_list = array();
        foreach($scope_ids as $scope_id){
            $scope_id = trim($scope_id);
            $get = OAuthScope::where('id', $scope_id)->first();
            if($get){
                $scope_list[] = $get->uuid;
            }
        }
        DB::table('client_connection_scopes')->where('connection_id', $id)->delete();
        foreach($scope_list as $scope){
            DB::table('client_connection_scopes')->insert(array('connection_id' => $id, 'scope_id' => $scope));
        }
        
        return parent::update($id, $request);
    }

}
