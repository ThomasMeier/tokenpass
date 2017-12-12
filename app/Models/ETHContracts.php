<?php

namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Tokenpass\Models\ETHContracts;

class ETHContracts extends Model
{
    protected $table = 'contract_abis';
    public $timestamps = true;

    public static function getAddressContracts($address) {
        return ETHContracts::where('eth_address', '=', $address)->get();
    }
}
