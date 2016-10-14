<?php

namespace Tokenpass\Repositories;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use DB, Input;

/*
* OAuthClientRepository
*/
class OAuthClientRepository extends APIRepository
{

    protected $model_type = 'Tokenpass\Models\OAuthClient';


    protected function modifyAttributesBeforeCreate($attributes) {
        $token_generator = app('Tokenly\TokenGenerator\TokenGenerator');

        // create a token
        if (!isset($attributes['id'])) {
            $attributes['id'] = $token_generator->generateToken(32, 'I');
        }

        if (!isset($attributes['secret'])) {
            $attributes['secret'] = $token_generator->generateToken(40, 'K');
        }
        
        return $attributes;
    }
    
    
    public function update(Model $model, $attributes)
    {
        $update = parent::update($model, $attributes);
        if(!$update){
            return false;
        }
        
        $endpoints = '';
        if(Input::get('endpoints')){
           $endpoints = trim(Input::get('endpoints')); 
        }
        DB::table('oauth_client_endpoints')->where('client_id', $model->id)->delete();
        if($endpoints != ''){
            $stamp = date('Y-m-d H:i:s');
            $vals = array('client_id' => $model->id, 'created_at' => $stamp, 'updated_at' => $stamp);
            $split_endpoints = explode("\n", $endpoints);
            foreach($split_endpoints as $endpoint){
                $vals['redirect_uri'] = trim($endpoint);
                DB::table('oauth_client_endpoints')->insert($vals);
            }
        }
        
        return $update;
    }

}
