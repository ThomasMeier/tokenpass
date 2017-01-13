<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Ramsey\Uuid\Uuid;

class AppCreditTransaction extends Model
{
    protected $table = 'app_credit_transactions';
    public $timestamps = true;
    
    public static function newTX($groupId, $accountId, $amount, $ref = null)
    {
        $tx = new AppCreditTransaction;
        $tx->app_credit_group_id = $groupId;
        $tx->app_credit_account_id = $accountId;
        $tx->amount = $amount;
        $tx->ref = $ref;
        $tx->uuid = Uuid::uuid4()->toString();
        $save = $tx->save();
        if(!$save){
            return false;
        }
        return $tx;
    }
    
    public function APIObject()
    {
        $get_group = AppCredits::find($this->app_credit_group_id);
        $get_account = AppCreditAccount::find($this->app_credit_account_id);
                
        $output = (object) [];
        $output->uuid = $this->uuid;
        $output->app_credit_group_uuid = $get_group->uuid;
        $output->account_uuid = $get_account->uuid;
        $output->account_name = $get_account->name;
        $output->amount = $this->amount;
        $output->ref = $this->ref;
        $output->created_at = (string)$this->created_at;
        $output->updated_at = (string)$this->updated_at;
        return $output;
    }
    
    
}
