<?php
namespace Tokenpass\Http\Controllers\API;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Log;
use Ramsey\Uuid\Uuid;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenpass\Events\CreditsUpdated;
use Tokenpass\Http\Controllers\Controller;
use Tokenpass\Jobs\PublishPubnubEvent;
use Tokenpass\Models\AppCreditAccount;
use Tokenpass\Models\AppCreditTransaction;
use Tokenpass\Models\AppCredits;
use Tokenpass\Models\OAuthClient;
use Tokenpass\Models\User;
use Tokenpass\Models\UserMeta;
use Tokenpass\OAuth\Facade\OAuthClientGuard;
use Tokenpass\OAuth\Facade\OAuthGuard;


class AppCreditsAPIController extends Controller
{


    public function listCreditGroups()
    {
        $api_user = $this->getAPIUser();
        $credit_groups = AppCredits::getCreditGroupsOwnedByUser($api_user['user_id']);
        $output = array('list' => array());
        foreach($credit_groups as $k => $group){
            $output['list'][] = $group->APIObject();
        }
        return Response::json($output);
    }
    
    public function newCreditGroup()
    {
        $api_user = $this->getAPIUser();
        $input = Input::all();
        if(!isset($input['name']) OR trim($input['name']) == ''){
            return Response::json(array('error' => 'App Credit Group name required'), 400);
        }
        $user_id = $api_user['user_id'];
        $uuid = Uuid::uuid4()->toString();
        $name = trim($input['name']);
        $active = true;
        $app_whitelist = null;
        if(isset($input['app_whitelist']) AND (is_array($input['app_whitelist']) OR trim($input['app_whitelist']) != '')){
            if(is_array($input['app_whitelist'])){
                $exp_list = $input['app_whitelist'];
            }
            else{
                $exp_list = explode("\n", trim($input['app_whitelist']));
            }
            $client_ids = array();
            foreach($exp_list as $k => $v){
                $client_id = trim($v);
                $find_client = OAuthClient::find($client_id);
                if(!$find_client){
                    return Response::json(array('error' => 'Invalid Client ID '.$client_id.' for App Credit Group'), 400);
                }
                $client_ids[] = $client_id;
            }
            $app_whitelist = join("\n", $client_ids);
        }
        $credit_group = new AppCredits;
        $credit_group->user_id = $user_id;
        $credit_group->uuid = $uuid;
        $credit_group->name = $name;
        $credit_group->active = $active;
        $credit_group->app_whitelist = $app_whitelist;
        $save = $credit_group->save();
        if(!$save){
            return Response::json(array('error' => 'Error creating App Credit Group'), 500);
        }
        return Response::json(array('credit_group' => $credit_group->APIObject()));
    }
    
    public function getCreditGroupDetails($groupId)
    {
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        return Response::json(array('credit_group' => $credit_group->APIObject()));
    }
    
    public function updateCreditGroup($groupId)
    {
        $input = Input::all();
        $credit_group = $this->checkClientHasCreditAccess($groupId, false);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        $name = $credit_group->name;
        if(isset($input['name']) AND trim($input['name']) != ''){
            $name = trim($input['name']);
        }
        $app_whitelist = $credit_group->app_whitelist;
        if(isset($input['app_whitelist']) AND (is_array($input['app_whitelist']) OR trim($input['app_whitelist']) != '')){
            if(is_array($input['app_whitelist'])){
                $exp_list = $input['app_whitelist'];
            }
            else{
                $exp_list = explode("\n", trim($input['app_whitelist']));
            }
            $client_ids = array();
            foreach($exp_list as $k => $v){
                $client_id = trim($v);
                $find_client = OAuthClient::find($client_id);
                if(!$find_client){
                    return Response::json(array('error' => 'Invalid Client ID '.$client_id.' for App Credit Group'), 400);
                }
                $client_ids[] = $client_id;
            }
            $app_whitelist = join("\n", $client_ids);
        }
        $credit_group->name = $name;
        $credit_group->app_whitelist = $app_whitelist;
        $save = $credit_group->save();
        if(!$save){
            return Response::json(array('error' => 'Error updating App Credit Group'), 500);
        }
        return Response::json(array('credit_group' => $credit_group->APIObject()));     
        
    }
    
    public function getCreditGroupTXHistory($groupId)
    {
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        $tx_history = $credit_group->transactionHistory();
        $output = array();
        foreach($tx_history as $k => $row){
            $new_row = array();
            $new_row['credit_group'] = $credit_group->uuid;
            $new_row['account'] = $row['account']['name'];
            $new_row['tokenpass_user'] = false;
            if($row['account']['tokenpass_user']){
                $new_row['tokenpass_user'] = $row['account']['tokenpass_user']['username'];
            }
            $new_row['account_uuid'] = $row['account']['uuid'];
            $new_row['tx_uuid'] = $row['uuid'];
            $new_row['amount'] = $row['amount'];
            $new_row['created_at'] = (string)$row['created_at'];
            $new_row['updated_at'] = (string)$row['updated_at'];
            $new_row['ref'] = $row['ref'];
            $output[] = $new_row;
        }
        return array('balance' => $credit_group->balance(), 'count' => count($output), 'transactions' => $output);
    }
    
    public function listCreditAccounts($groupId)
    {
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        $accounts = $credit_group->getAccounts();
        $output = array();
        foreach($accounts as $k => $account){
            $row = (array)$account;
            unset($row['id']);
            unset($row['app_credit_group_id']);
            $output[] = $row;
        }
        return array('balance' => $credit_group->balance(), 'count' => count($output), 'accounts' => $output);
    }
    
    public function newCreditAccount($groupId)
    {
        $input = Input::all();
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        if(!isset($input['name']) OR trim($input['name']) == ''){
            return Response::json(array('error' => 'App Credit account name required'), 400);
        } 
        $name = trim(htmlentities($input['name']));
        $check_exists = AppCreditAccount::where('app_credit_group_id', $credit_group->id)->where('name', $name)->first();
        if($check_exists){
            return Response::json(array('error' => 'An account with this name already exists'), 400);
        }
        $account = new AppCreditAccount;
        $account->app_credit_group_id = $credit_group->id;
        $account->name = $name;        
        $account->uuid = Uuid::uuid4()->toString();
        $save = $account->save();
        if(!$save){
            return Response::json(array('error' => 'Error saving App Credit Group account'), 500);
        }
        return $this->getCreditAccountDetails($groupId, $account->uuid);            
    }
    
    //debit balances of one or more accounts
    public function debitAccounts($groupId)
    {
        $input = Input::all();
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        if(!isset($input['accounts']) OR !is_array($input['accounts'])){
            return Response::json(array('error' => 'Array of accounts to update required'), 400);
        }
        $txs = array();
        foreach($input['accounts'] as $k => $row){
            if(!isset($row['account'])){
                return Response::json(array('error' => 'Missing key "account"'), 400);
            }
            if(!isset($row['amount'])){
                return Response::json(array('error' => 'Missing key "amount"'), 400);
            }            
            $account = $row['account'];
            $source_account = $credit_group->getAccount($account, false);
            if(!$source_account){
                return Response::json(array('error' => 'Invalid account "'.$account.'"'), 400);
            }
            $amount = abs(intval($row['amount']));
            $destination_account = null; //credit this same balance to a destination account... if null then use default system account
            if(isset($row['destination']) AND trim($row['destination']) != ''){
                $find_destination = $credit_group->getAccount($row['destination']);
                if(!$find_destination){
                    return Response::json(array('error' => 'Destination account '.$row['destination'].' does not exist'), 400);
                }
                $destination_account = $find_destination;
            }
            else{
                //use system default as debit destination
                $default_destination = $credit_group->getDefaultAccount();
                if(!$default_destination){
                    return Response::json(array('error' => 'Error loading default debit destination account'), 400);
                }
                $destination_account = $default_destination;
            }
            $ref = null;
            if(isset($row['ref'])){
                $ref = $row['ref'];
            }
            
            //create one negative TX for the account, create one positive TX for destination (double entry)
            list ($tx_item, $error_response) = DB::transaction(function() use ($amount, $credit_group, $source_account, $destination_account, $ref) {
                $debit_amount = 0 - $amount;
                $credit_amount = $amount;
                $debit_tx = AppCreditTransaction::newTX($credit_group->id, $source_account->id, $debit_amount, $ref);
                if(!$debit_tx){
                    return [null, Response::json(array('error' => 'Error saving debit transaction'), 500)];
                }
                $credit_tx = AppCreditTransaction::newTX($credit_group->id, $destination_account->id, $credit_amount, $ref);
                if(!$credit_tx){
                    $debit_tx->delete();
                    return [null, Response::json(array('error' => 'Error saving credit entry for debit transaction'), 500)];
                }

                // send the events
                event(new CreditsUpdated($source_account));
                event(new CreditsUpdated($destination_account));

                return [array('debit' => $debit_tx->APIObject(), 'credit' => $credit_tx->APIObject()), null];
            });
            if ($error_response !== null) {
                return $error_response;
            }
            $txs[] = $tx_item;
        }
        return Response::json(array('transactions' => $txs));
    }
    
    //credit balances of one or more accounts
    public function creditAccounts($groupId)
    {
        $input = Input::all();
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        if(!isset($input['accounts']) OR !is_array($input['accounts'])){
            return Response::json(array('error' => 'Array of accounts to update required'), 400);
        }
        $txs = array();
        foreach($input['accounts'] as $k => $row){
            if(!isset($row['account'])){
                return Response::json(array('error' => 'Missing key "account"'), 400);
            }
            if(!isset($row['amount'])){
                return Response::json(array('error' => 'Missing key "amount"'), 400);
            }            
            $account = $row['account'];
            $recipient_account = $credit_group->getAccount($account, false);
            if(!$recipient_account){
                return Response::json(array('error' => 'Invalid account "'.$account.'"'), 400);
            }
            $amount = abs(intval($row['amount']));
            $source_account = null; //debit this same balance from a source account... if null then use default system account
            if(isset($row['source']) AND trim($row['source']) != ''){
                $find_source = $credit_group->getAccount($row['source']);
                if(!$find_source){
                    return Response::json(array('error' => 'Source account '.$row['source'].' does not exist'), 400);
                }
                $source_account = $find_source;
            }
            else{
                //use system default as debit destination
                $default_source = $credit_group->getDefaultAccount();
                if(!$default_source){
                    return Response::json(array('error' => 'Error loading default credit source account'), 400);
                }
                $source_account = $default_source;
            }
            $ref = null;
            if(isset($row['ref'])){
                $ref = $row['ref'];
            }
            
            //create one positive TX for the account, create one negative TX for source (double entry)
            list ($tx_item, $error_response) = DB::transaction(function() use ($amount, $credit_group, $recipient_account, $source_account, $ref) {
                $credit_amount = $amount;            
                $debit_amount = 0 - $amount;
                $credit_tx = AppCreditTransaction::newTX($credit_group->id, $recipient_account->id, $credit_amount, $ref);
                if(!$credit_tx){
                    return [null, Response::json(array('error' => 'Error saving credit transaction'), 500)];
                }
                $debit_tx = AppCreditTransaction::newTX($credit_group->id, $source_account->id, $debit_amount, $ref);
                if(!$debit_tx){
                    $credit_tx->delete();
                    return [null, Response::json(array('error' => 'Error saving debit entry for credit transaction'), 500)];
                }

                // send the events
                event(new CreditsUpdated($recipient_account));
                event(new CreditsUpdated($source_account));

                return [array('credit' => $credit_tx->APIObject(), 'debit' => $debit_tx->APIObject()), null];
            });
            if ($error_response !== null) {
                return $error_response;
            }
            $txs[] = $tx_item;
        }
        return Response::json(array('transactions' => $txs));
    }
    
    public function getCreditAccountDetails($groupId, $accountId)
    {
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        $account = $credit_group->getAccount($accountId);
        if(!$account){
            return Response::json(array('error' => 'App Credit account not found'), 404);
        }
        $output = array();
        $output['name'] = $account->name;
        $output['uuid'] = $account->uuid;
        $output['balance'] = $account->balance;
        $output['tokenpass_user'] = $account->tokenpass_user;
        $output['created_at'] = (string)$account->created_at;
        $output['updated_at'] = (string)$account->updated_at;
        return Response::json(array('account' => $output));
    }
    
    public function getCreditAccountTXHistory($groupId, $accountId)
    {
        $credit_group = $this->checkClientHasCreditAccess($groupId);
        if(get_class($credit_group) != AppCredits::class){
            return $credit_group; //return alt response (usually error)
        }
        $account = $credit_group->getAccount($accountId);
        if(!$account){
            return Response::json(array('error' => 'App Credit account not found'), 404);
        }
        $tx_history = $credit_group->transactionHistory($account->uuid);
        $history = array();
        foreach($tx_history as $k => $row){
            $new_row = array();
            $new_row['credit_group'] = $credit_group->uuid;
            $new_row['tx_uuid'] = $row['uuid'];
            $new_row['amount'] = $row['amount'];
            $new_row['created_at'] = (string)$row['created_at'];
            $new_row['updated_at'] = (string)$row['updated_at'];
            $new_row['ref'] = $row['ref'];
            $history[] = $new_row;
        }        
        $output = array('account' => $account->toArray(), 'count' => count($history), 'transactions' => $history);
        unset($output['account']['id']);
        unset($output['account']['app_credit_group_id']);       
        return Response::json($output); 
    }
    
    
    protected function checkClientHasCreditAccess($groupId, $check_whitelist = true)
    {
        $api_user = $this->getAPIUser();
        $credit_group = AppCredits::where('uuid', $groupId)->first();
        if(!$credit_group){
            return Response::json(array('error' => 'App Credit Group not found'), 404);
        }
        $app_whitelist = explode("\n", $credit_group->app_whitelist);
        if($check_whitelist){
            if(!in_array($api_user['client']->id, $app_whitelist)){
                return Response::json(array('error' => 'Your app does not have access to this App Credit Group'), 403);
            }
        }
        else{
            if($api_user['user_id'] != 0){
                if($api_user['user_id'] != $credit_group->user_id){
                    return Response::json(array('error' => 'You do not have access to this App Credit Group'), 403);
                }
            }
        }   
        return $credit_group;
    }
    
    protected function getAPIUser()
    {
        $client = OAuthClientGuard::oauthClient();
        if($client->user_id == 0){
            return array('client' => $client, 'user' => false, 'user_id' => 0);
        }
        return array('client' => $client, 'user' => User::find($client->user_id), 'user_id' => $client->user_id);
    }
    
}
