<?php

namespace Tokenpass\Http\Controllers\PlatformAdmin;

use Illuminate\Support\Facades\Log;
use Tokenpass\Repositories\ProvisionalRepository;
use Tokenpass\Models\OAuthClient;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\PlatformAdmin\Controllers\ResourceController;
use Tokenpass\Models\User;

class PromisesController extends ResourceController
{

    protected $view_prefix      = 'promise';
    protected $repository_class = ProvisionalRepository::class;

    public function __construct()
    {
        $this->middleware('sign');

    }

    protected function getValidationRules() {
        return [
            'source'      => 'required|max:255',
            'destination' => 'required|max:255',
            'asset'       => 'required|max:255',
            'quantity'    => 'required|numeric',
            'expiration'    => 'max:255',
            'ref'    => 'max:255',
            'txid'    => 'max:255',
            'fingerprint'    => 'max:255',
            'client_id'    => '',
            'user_id'    => 'integer',
            'note'    => 'max:255',
        ];
    }    

    protected function modifyVarsBeforeCreate($create_vars) {
        $date = date('Y-m-d H:i:s');

        $create_vars['quantity'] = CurrencyUtil::valueToSatoshis($create_vars['quantity']);
        $create_vars['created_at'] = $date;
        $create_vars['updated_at'] = $date;
        $create_vars['pseudo'] = 0;

        if(isset($create_vars['expiration'])){
            $create_vars['expiration'] = strtotime(trim($create_vars['expiration']));
        }    

        return $create_vars;
    }

    protected function modifyVarsBeforeUpdate($update_vars) {
        $update_vars['quantity'] = CurrencyUtil::valueToSatoshis($update_vars['quantity']);
        if(isset($update_vars['expiration'])){
            $update_vars['expiration'] = strtotime(trim($update_vars['expiration']));
        }
        return $update_vars;
    }
    
    protected function modifyViewData_edit($view_data) {
        
        $view_data['clients'] = OAuthClient::all();
        if(!$view_data['clients']){
            $view_data['clients'] = array();
        }
        $view_data['users'] = User::all();
        if(!$view_data['users']){
            $view_data['users'] = array();
        }        
        
        return $view_data;
    }
    
    protected function modifyViewData_create($view_data) {
        
        $view_data['clients'] = OAuthClient::all();
        if(!$view_data['clients']){
            $view_data['clients'] = array();
        }
        $view_data['users'] = User::all();
        if(!$view_data['users']){
            $view_data['users'] = array();
        }             
        
        return $view_data;
    }

}
