<?php

namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Tokenpass\Models\ETHContracts;

class ETHContractABI extends Model
{
    protected $table = 'contracts_users_balances';
    public $timestamps = true;

    public static function updateBalance($address) {

    }
}
