<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Tokenly\LaravelEventLog\Facade\EventLog;

class AppCredits extends Model
{
    protected $table = 'app_credit_groups';
    public $timestamps = true;
    protected $fillable = ['name', 'uuid', 'user_id', 'app_whitelist'];
    
    
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
        $output = (object) [];
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
                    $tokenpass_user = array();
                    $tokenpass_user['uuid'] = $user->uuid;
                    $tokenpass_user['slug'] = $user->slug;
                    $tokenpass_user['username'] = $user->username;
                    $account_ids[$account->id]->tokenpass_user = $tokenpass_user;
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
            $tokenpass_user = array();
            $tokenpass_user['uuid'] = $user->uuid;
            $tokenpass_user['slug'] = $user->slug;
            $tokenpass_user['username'] = $user->username;
            $get->tokenpass_user = $tokenpass_user;
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
            if($get_account AND $tx['app_credit_account_id'] == $get_account->id){
                $txs[$k]['account'] = $get_account;
            }
            else{
                if(isset($known_accounts[$tx['app_credit_account_id']])){
                    $txs[$k]['account'] = $known_accounts[$tx['app_credit_account_id']];
                }
                else{
                    $tx_account = $this->getAccount($tx['app_credit_account_id'], false, true);
                    $txs[$k]['account'] = $tx_account;
                    $known_accounts[$tx['app_credit_account_id']] = $tx_account;
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
    
    public function newAccount($name)
    {
        $get = $this->getAccount($name);
        if($get){
            throw new Exception('Account '.$name.' already exists');
        }
        $account = new AppCreditAccount;
        $account->app_credit_group_id = $this->id;
        $account->uuid = Uuid::uuid4()->toString();
        $account->name = $name;
        $save = $account->save();
        if(!$save){
            return false;
        }
        $get = $this->getAccount($account->uuid);
        return $get;
    }
    
    public function credit($account, $amount, $source = null, $ref = null)
    {
        $get_account = $this->getAccount($account, false);
        if(!$get_account){
            throw new Exception('Account '.$account.' not found');
        }
        
        if($source === null OR trim($source) == ''){
            $default_source = $this->getDefaultAccount();
            if(!$default_source){
                throw new Exception('Error loading default tx source');
            }
            $use_source = $default_source->id;
        }
        else{
            $get_source = $this->getAccount($source);
            if(!$get_source){
                throw new Exception('Source account '.$source.' not found');
            }
            $use_source = $get_source->id;
        }
        
        return DB::transaction(function() use ($amount, $get_account, $use_source, $ref) {
            $amount = abs(intval($amount));
            $credit_amount = $amount;
            $debit_amount = 0 - $amount;
            $credit_tx = AppCreditTransaction::newTX($this->id, $get_account->id, $credit_amount, $ref);
            if(!$credit_tx){
                throw new Exception('Error saving credit transaction');
            }
            
            $debit_tx = AppCreditTransaction::newTX($this->id, $use_source, $debit_amount, $ref);        
            if(!$debit_tx){
                $credit_tx->delete();
                throw new Exception('Error saving debit entry for credit transaction');
            }
            
            return array('credit' => $credit_tx, 'debit' => $debit_tx);
        });

    }
    
    public function debit($account, $amount, $destination = null, $ref = null)
    {
        $get_account = $this->getAccount($account, false);
        if(!$get_account){
            throw new Exception('Account '.$account.' not found');
        }
        
        if($destination === null OR trim($destination) == ''){
            $default_destination = $this->getDefaultAccount();
            if(!$default_destination){
                throw new Exception('Error loading default tx destination');
            }
            $use_destination = $default_destination->id;
        }
        else{
            $get_destination = $this->getAccount($destination);
            if(!$get_destination){
                throw new Exception('Destination account '.$destination.' not found');
            }
            $use_destination = $get_destination->id;
        }
        
        return DB::transaction(function() use ($amount, $get_account, $use_destination, $ref) {
            $amount = abs(intval($amount));
            $credit_amount = $amount;
            $debit_amount = 0 - $amount;
            
            $debit_tx = AppCreditTransaction::newTX($this->id, $get_account->id, $debit_amount, $ref);
            if(!$debit_tx){
                throw new Exception('Error saving debit transaction');
            }
            
            $credit_tx = AppCreditTransaction::newTX($this->id, $use_destination, $credit_amount, $ref);        
            if(!$credit_tx){
                $debit_tx->delete();
                throw new Exception('Error saving credit entry for debit transaction');
            }
            
            return array('debit' => $debit_tx, 'credit' => $credit_tx);
        });
    }
    
}
