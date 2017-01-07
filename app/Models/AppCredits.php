<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;

class AppCredits extends Model
{
    protected $table = 'app_credit_groups';
    public $timestamps = true;
    
    
    public function numAccounts()
    {
        return DB::table('app_credit_accounts')->where('app_credit_group_id', $this->id)->count();
    }
    
    public function getAccounts($with_balances = true)
    {
        $accounts = DB::table('app_credit_accounts')->where('app_credit_group_id', $this->id)->get();
        if($with_balances){
            $txs = DB::table('app_credit_transactions')->where('app_credit_group_id', $this->id)->get();
            $account_ids = array();
            foreach($accounts as $k => $account){
                $account_ids[$account->id] = $account;
                $account_ids[$account->id]->balance = 0;
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
        $get = DB::table('app_credit_accounts')->where('app_credit_group_id', $this->id)
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
            $get->balance = DB::table('app_credit_transactions')->where('app_credit_account_id', $get->id)->sum('amount');
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
            $txs = DB::table('app_credit_transactions')->where('app_credit_account_id', $get_account->id)->get()->toArray();
        }
        else{
            $txs = DB::table('app_credit_transactions')->where('app_credit_group_id', $this->id)->get()->toArray();
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
        return DB::table('app_credit_transactions')->where('app_credit_group_id', $this->id)->sum('amount');
    }
    
    public function isBalanced()
    {
        $balance = $this->balance();
        if($balance !== 0){
            return false;
        }
        return true;
    }
    
}
