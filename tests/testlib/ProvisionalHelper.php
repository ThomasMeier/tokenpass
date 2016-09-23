<?php

use Illuminate\Support\Facades\Log;

/*
* ProvisionalHelper
*/
class ProvisionalHelper
{
    public function __construct() {
    }


    public function newSampleProvisional($override_vars=[]) {
        $provisional_vars = array_merge($this->defaultVars(), $override_vars);
        $provisional = app('Tokenpass\Repositories\ProvisionalRepository')->create($provisional_vars);
        return $provisional;
    }

    public function defaultVars() {
        $date = date('Y-m-d H:i:s');
        return [
            'source'      => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'destination' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU',
            'quantity'    => 15,
            'asset'       => 'TOKENLY',
            'created_at'  => $date,
            'updated_at'  => $date,
            'pseudo'      => 0,

            'fingerprint' => null,
            'txid'        => null,
            'expiration'  => null,
            'ref'         => null,
            'client_id'   => 'AAAAAAAAAAAAAAAAAAAAAA',
            'user_id' => 0,
            'note' => null,

        ];
    }
}
