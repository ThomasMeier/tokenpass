<?php

namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Tokenpass\Models\ETHContractsUsersBalances;

class ETHContractsUsersBalances extends Model
{
    protected $table = 'contracts_users_balances';
    public $timestamps = true;

    public static function updateUserBalance($address, $contract_id, $balance)
    {
        $currentBalance = ETHContractsUsersBalances::where([
            ['contract_id', '=', $contract_id],
            ['eth_address','=',$address]]);
        $currentBalance->balance = $balance;
        $currentBalance->save();
    }
}
