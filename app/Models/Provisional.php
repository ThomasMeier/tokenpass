<?php
namespace Tokenpass\Models;
use Exception, DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Provisional extends Model
{

    protected static $unguarded = true;

    protected $table = 'provisional_tca_txs';


    public static function getProofMessage($address, $client_id)
    {
        return $address.'_'.Provisional::getProofHash($client_id);
    }
    
    public static function getProofHash($client_id)
    {
        return hash('sha256', $client_id);
    }
    
    public static function getTotalPromised($address, $asset, $promise_total = 0, $ignore_id = false)
    {
        $other_promises = DB::table('provisional_tca_txs')
                        ->where('source', $address)
                        ->where('asset', $asset)
                        ->where('pseudo', 0)->get();
        if($other_promises){
            foreach($other_promises as $promise){
                if($promise->id == $ignore_id){
                    continue;
                }
                $promise_total += $promise->quantity;
            }
        }
        return $promise_total;
    }
    
    public static function checkValidPromisedAmount($address, $asset, $promise_total)
    {
        $xchain = app('Tokenly\XChainClient\Client');
        $balances = false;
        try{
            $balances = $xchain->getBalances($address, true);
            Log::debug("$address \$balances=".json_encode($balances, 192));
        }
        catch(Exception $e){
            throw new Exception('Error checking source address '.$address.' balances');
        }
        
        if(!$balances){
            throw new Exception('Could not get balances for source address '.$address);
        }
        
        $valid_balance = false;
        if(isset($balances[$asset])){
            if($balances[$asset] >= $promise_total){
                $valid_balance = true;
            }
        }
        else{
            $balances[$asset] = 0;
        }
        $output = array('valid' => $valid_balance, 'balance' => $balances[$asset]);
        return $output;
    }
    
    public static function getAddressPromises($address)
    {
        $get = Provisional::where('destination', $address)->where('pseudo', 0)->get();
        return $get;
    }
    
    public static function getUserOwnedPromises($user_id)
    {
        $get = Provisional::select('provisional_tca_txs.*')
        ->leftJoin('coin_addresses', 'coin_addresses.address', '=', 'provisional_tca_txs.source')
        ->where('provisional_tca_txs.user_id', $user_id)->where('provisional_tca_txs.pseudo', 0)->where('coin_addresses.active_toggle', 1)->get();
        return $get;
    }
    
    public function getRefData()
    {
        $exp = explode(',', $this->ref);
        $data = array();
        foreach($exp as $group){
            $exp2 = explode(':', $group);
            if(isset($exp2[1])){
                $data[$exp2[0]] = $exp2[1];
            }
            else{
                $data[] = $exp2[0];
            }
        }
        return $data;
    }
    
    public static function joinRefData($data)
    {
        foreach($data as $k => $row){
            if(!is_integer($k)){
                $row = $k.':'.$row;
            }
            $data[$k] = $row;
        }
        $joined =  join(',', $data);
        return $joined;
    }
    
    
    public function invalidate()
    {
        //send notifications that this promise has been invalidated, most likely from not enough real balance
        if($this->user_id > 0){
            $this->sendInvalidationNotifications();
        }
        return $this->delete();
    }
    
    public function sendInvalidationNotifications()
    {
        $lender = User::find($this->user_id);
        if($lender){
            $lendee = Address::where('address', $this->destination)->where('verified', 1)->first();
            if($lendee){
                $lendee = $lendee->user();
            }                                 
            $notify_data = array('promise' => $this, 'lender' => $lender, 'lendee' => $lendee);
            //notify lender
            $lender->notify('emails.loans.invalidated-lender', 'TCA loan for '.$this->asset.' invalidated '.date('Y/m/d'), $notify_data);                        
            //notify lendee
            if($lendee){
                $lendee->notify('emails.loans.invalidated-lendee', 'TCA loan for '.$this->asset.' invalidated '.date('Y/m/d'), $notify_data);
            }
        }        
    }
    
    public function formatQuantity()
    {
        $q = $this->convertQuantity();
        return rtrim(rtrim(number_format($q, 8), "0"), ".");
    }
    
    public function convertQuantity()
    {
        return round($this->quantity / 100000000, 8);
    }
}
