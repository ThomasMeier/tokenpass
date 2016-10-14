<?php

namespace Tokenpass\Http\Controllers\PlatformAdmin;

use Illuminate\Support\Facades\Log;
use Tokenpass\Repositories\OAuthClientRepository;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\PlatformAdmin\Controllers\ResourceController;
use Input;

class ClientController extends ResourceController
{

    protected $view_prefix      = 'client';
    protected $repository_class = OAuthClientRepository::class;
    
    public function __construct()
    {
        $this->middleware('sign');

    }

    protected function getValidationRules() {
        return [
            'name' => 'required|max:255',
            'app_link' => 'url',
            'privileges' => '',
        ];
    }    


}
