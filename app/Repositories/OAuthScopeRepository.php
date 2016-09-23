<?php

namespace Tokenpass\Repositories;

use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Exception;

/*
* OAuthScopeRepository
*/
class OAuthScopeRepository extends APIRepository
{

    protected $model_type = 'Tokenpass\Models\OAuthScope';

}
