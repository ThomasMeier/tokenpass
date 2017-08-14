<?php

namespace Tokenpass\Repositories;

use Exception;
use Tokenly\LaravelApiProvider\Repositories\BaseRepository;
use Tokenpass\Models\Provisional;

/*
* ProvisionalRepository
*/
class ProvisionalRepository extends BaseRepository
{

    protected $model_type = 'Tokenpass\Models\Provisional';


    function findPromiseTx($email) {
        return Provisional::where('destination', "email:$email")->get();
    }

}
