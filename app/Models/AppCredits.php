<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Ramsey\Uuid\Uuid;

class AppCredits extends Model
{
    protected $table = 'app_credit_groups';
    public $timestamps = true;
    
    
    public static function getCreditGroupsOwnedByUser($userId)
    {
        if($userId == 0){
            $get = AppCredits::all();
        }
        else{
            $get = AppCredits::where('user_id', $userId)->get();
        }
        if(!$get){
            return array();
        }
        return $get;
    }
    
    public function APIObject()
    {
        $output = new stdClass;
        $output->name = $this->name;
        $output->uuid = $this->uuid;
        $output->active = boolval($this->active);
        $output->app_whitelist = explode("\n", $this->app_whitelist);
        $output->created_at = (string)$this->created_at;
        $output->updated_at = (string)$this->updated_at;
        return $output;
    }
    
    public function numAccounts()
    {
        return AppCreditAccount::where('app_credit_group_id', $this->id)->count();
    }
    
    public function getAccounts($with_balances = true)
    {
        $accounts = AppCreditAccount::where('app_credit_group_id', $this->id)->orderBy('id', 'desc')->get();
        if($with_balances){
            $txs = AppCreditTransaction::where('app_credit_group_id', $this->id)->get();
            $account_ids = array();
            foreach($accounts as $k => $account){
                $account_ids[$account->id] = $account;
                $account_ids[$account->id]->balance = 0;
                $account_ids[$account->id]->tokenpass_user = false;
                $user = User::where('uuid', $account->name)->first();
                if($user){
                    $account_ids[$account->id]->tokenpass_user = array();
                    $account_ids[$account->id]->tokenpass_user['uuid'] = $user->uuid;
                    $account_ids[$account->id]->tokenpass_user['slug'] = $user->slug;
                    $account_ids[$account->id]->tokenpass_user['username'] = $user->username;
                }                
            }
            foreach($txs as $tx){
                $id = $tx->app_credit_account_id;
                if(isset($account_ids[$id])){
                    if(!isset($account_ids[$id]->balance)){
                        $account_ids[$id]->balance = 0;
                    }
                    $account_ids[$id]->balance += $tx->amount;
                }
            }
            $accounts = array_values($account_ids);
        }
        return $accounts;
    }
    
    public function getAccount($name, $with_balance = true, $use_real_id = false)
    {
        $get = AppCreditAccount::where('app_credit_group_id', $this->id)
                    ->where(function($q) use($name, $use_real_id){
                        if($use_real_id){
                            $q->where('id', $name);
                        }
                        else{
                            $q->where('name', $name);
                            $q->orWhere('uuid', $name);
                        }
                    })->first();
        if(!$get){
            return false;
        }
        if($with_balance){
            $get->balance = AppCreditTransaction::where('app_credit_account_id', $get->id)->sum('amount');
        }
        $get->tokenpass_user = false;
        $user = User::where('uuid', $get->name)->first();
        if($user){
            $get->tokenpass_user = array();
            $get->tokenpass_user['uuid'] = $user->uuid;
            $get->tokenpass_user['slug'] = $user->slug;
            $get->tokenpass_user['username'] = $user->username;
        }
        return $get;
    }
    
    public function transactionHistory($account = false)
    {
        $get_account = false;
        if($account){
            $txs = false;
            $get_account = $this->getAccount($account, false);
            if(!$get_account){
                return $txs;
            }
            $txs = AppCreditTransaction::where('app_credit_account_id', $get_account->id)->orderBy('id', 'desc')->get()->toArray();
        }
        else{
            $txs = AppCreditTransaction::where('app_credit_group_id', $this->id)->orderBy('id', 'desc')->get()->toArray();
        }
        $known_accounts = array();
        foreach($txs as $k => $tx){
            if($get_account AND $tx->app_credit_account_id == $get_account->id){
                $txs[$k]->account = $get_account;
            }
            else{
                if(isset($known_accounts[$tx->app_credit_account_id])){
                    $txs[$k]->account = $known_accounts[$tx->app_credit_account_id];
                }
                else{
                    $tx_account = $this->getAccount($tx->app_credit_account_id, false, true);
                    $txs[$k]->account = $tx_account;
                    $known_accounts[$tx->app_credit_account_id] = $tx_account;
                }
            }
        }
        return $txs;
    }
    
    public function balance($account = false)
    {
        if($account){
            $get_account = $this->getAccount($account);
            if(!$get_account){
                return false;
            }
            return $get_account->balance;
        }
        return AppCreditTransaction::where('app_credit_group_id', $this->id)->sum('amount');
    }
    
    public function isBalanced()
    {
        $balance = $this->balance();
        if($balance !== 0){
            return false;
        }
        return true;
    }
    
    public function getDefaultAccount()
    {
        $default_name = '_';
        $get = $this->getAccount($default_name);
        if(!$get){
            //create
            $account = new AppCreditAccount;
            $account->app_credit_group_id = $this->id;
            $account->name = $default_name;
            $account->uuid = Uuid::uuid4()->toString();
            $save = $account->save();
            if(!$save){
                return false;
            }
            $get = $this->getAccount($account->uuid);
        }
        return $get;
    }
    
}
