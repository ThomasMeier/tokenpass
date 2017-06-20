<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tokenpass\Models\Address;
use Tokenly\CurrencyLib\CurrencyUtil;
use Tokenly\LaravelEventLog\Facade\EventLog;

class Address extends Model
{
    protected $table = 'coin_addresses';
    public $timestamps = true;

    // protected $fillable = ['user_id', 'type', 'address', 'label', 'verified', 'public', 'active_toggle', 'send_monitor_id', 'receive_monitor_id', 'xchain_address_id', ];
    protected static $unguarded = true;


    protected $casts = [
        'verified'             => 'boolean',
        'public'               => 'boolean',
        'from_api'             => 'boolean',
        'active_toggle'        => 'boolean',
        'login_toggle'         => 'boolean',
        'second_factor_toggle' => 'boolean',
        'pseudo'               => 'boolean',
    ];

    public function isPseudoAddress() {
        return $this['pseudo'];
    }

    
    public static function getAddressList($userId, $public = null, $active_toggle = 1, $verified_only = false, $login_toggle=null)
    {
        $get = Address::where('user_id', '=', $userId);
        if($verified_only){
            $get = $get->where('verified', 1);
        }
        if($public !== null){
            $get = $get->where('public', '=', intval($public));
        }
        if($active_toggle !== null){
            $get = $get->where('active_toggle', '=', intval($active_toggle));
        }
        if($login_toggle !== null){
            $get = $get->where('login_toggle', '=', intval($login_toggle));
        }
        return $get->orderBy('id', 'asc')->get();
    }
    
    public static function getAddressBalances($address_id, $filter_disabled = false, $and_provisional = true, $subtract_loans = false)
    {
        $address = Address::find($address_id);
        if(!$address OR $address->verified != 1 OR $address->active_toggle != 1){
            return false;
        }
        $balances = array();
        $get = DB::table('address_balances')->where('address_id', '=', $address->id)->get();
        if($get AND count($get) > 0){
            foreach($get as $row){
                $balances[$row->asset] = intval($row->balance);
            }
        }
        if($and_provisional){
            //add amounts from provisional txs
            $get_provisional = DB::table('provisional_tca_txs')->where('destination', $address->address)->get();
            if($get_provisional){
                foreach($get_provisional as $prov_tx){
                    if($prov_tx->user_id > 0){
                        $prov_address = Address::where('address', $prov_tx->source)->first();
                        if($prov_address){
                            if($prov_address->active_toggle == 0){
                                continue;
                            }
                        }
                        $disabled = Address::getDisabledTokens($prov_tx->user_id);
                        if(in_array($prov_tx->asset, $disabled)){
                            continue;
                        }
                    }
                    if(isset($balances[$prov_tx->asset])){
                        $balances[$prov_tx->asset] += $prov_tx->quantity;
                    }
                    else{
                        $balances[$prov_tx->asset] = $prov_tx->quantity;
                    }
                }
            }
        }
        if($subtract_loans){
            $loans = Provisional::getUserOwnedPromises($address->user_id);
            if($loans){
                foreach($loans as $loan){
                    if($loan->source == $address->address){
                        if(isset($balances[$loan->asset])){
                            $balances[$loan->asset] -= intval($loan->quantity);
                        }
                    }
                }
            }
        }        
        if($filter_disabled){
            $disabled = Address::getDisabledTokens($address->user_id);
            foreach($disabled as $asset){
                if(isset($balances[$asset])){
                    unset($balances[$asset]);
                }
            }
        }
        return $balances;
    }
    
    public static function updateAddressBalances($address_id, $balance_list)
    {
        $address = Address::find($address_id);
        if(!$address OR $address->verified != 1 OR $address->active_toggle != 1){
            return false;
        }

        return self::updateAddressBalancesTable($address, $balance_list);
    }


    public static function getAllUserBalances($user_id, $filter_disabled = false, $and_provisional = true, $subtract_loans = false)
    {
        $address_list = Address::getAddressList($user_id, null, true);
        if(!$address_list OR count($address_list) == 0){
            return array();
        }
        $balances = array();
        $owned_addresses = array();
        foreach($address_list as $address){
            $addr_balances = Address::getAddressBalances($address->id, false, $and_provisional);
            $owned_addresses[] = $address->address;
            if(is_array($addr_balances)){
                foreach($addr_balances as $asset => $val){
                    if(!isset($balances[$asset])){
                        $balances[$asset] = intval($val);
                    }
                    else{
                        $balances[$asset] += intval($val);
                    }
                }
            }
        }
        if($subtract_loans){
            $loans = Provisional::getUserOwnedPromises($user_id);
            if($loans){
                foreach($loans as $loan){
                    if(!in_array($loan->destination, $owned_addresses)){
                        if(isset($balances[$loan->asset])){
                            $balances[$loan->asset] -= intval($loan->quantity);
                        }
                    }
                }
            }
        }
        if($filter_disabled){
            $disabled = Address::getDisabledTokens($user_id);
            foreach($disabled as $asset){
                if(isset($balances[$asset])){
                    unset($balances[$asset]);
                }
            }
        }
        return $balances;
    }

    public static function getInstantVerifyMessage($user, $regen = true)
    {
        $get = UserMeta::getMeta($user->id, 'instant_verify_message', true);
        if($regen AND (!$get OR ($get AND ((time() - strtotime($get->updated_at)) > Config::get('tokenpass.instant_verify_code_expire'))))){
            return self::setInstantVerifyMessage($user);
        }
        if(!$get){
            return false;
        }
        return $get->meta_value;
    }
    
    public static function setInstantVerifyMessage($user)
    {
        $entropy = Address::getSecureCodeGeneration(8);
        $message = hash('sha256', $user->uuid . ' ' . $entropy);
        UserMeta::setMeta($user->id, 'instant_verify_message', $message);
        return $message;
    }
    
    public static function getUserVerificationCode($user, $type='readable')
    {
        $result = [];
        if(!$user){
            return false;
        }
        $sign_auth = UserMeta::getMeta($user->id,'sign_auth');
        if ($sign_auth == false) {
            Address::getVerificationType($type, $user);
            $sign_auth = UserMeta::getMeta($user->id, 'sign_auth');
        }
        if ($sign_auth != false) {
            $result['seconds'] = UserMeta::getDurationValueHasBeenSet($user->id, $sign_auth);
            $result['extra'] = UserMeta::getMetaExtraValue($user->id, $sign_auth);
        }
        if ($result['seconds'] > Config::get('tokenpass.crypto_verify_code_expire') OR $result['extra'] == 'signed') {
            Address::getVerificationType($type, $user);
        }

        $result['user_meta'] = UserMeta::getMeta($user->id,'sign_auth');

        return $result;
    }

    private static function getVerificationType($type, $user=null) {
        switch ($type) {
            case 'complex':
                UserMeta::setMeta($user->id,'sign_auth',Address::getInstantVerifyMessage($user),0,0,'unsigned');
        break;
            case 'readable':
                UserMeta::setMeta($user->id,'sign_auth',Address::getSecureCodeGeneration() .' '. date('Y/m/d'),0,0,'unsigned');
        break;
            case 'complex readable':
                UserMeta::setMeta($user->id,'sign_auth',Address::getSecureCodeGeneration(8),0,0,'unsigned');
        break;
            case 'simple':
                UserMeta::setMeta($user->id,'sign_auth',Address::getSecureCodeGeneration(),0,0,'unsigned');
        break;
        }
    }

    public static function getSecureCodeGeneration($entropy=null, $language=null, $no_prefix=false)
    {
        if(is_null($language)) {
            $file_content = file_get_contents(base_path() . "/database/wordlists/english.txt");
        } else {
            $file_content = file_get_contents(base_path() . '/database/wordlists/' . $language .'txt');
        }
        $dictionary = explode(PHP_EOL, $file_content);

        if(is_null($entropy)) {
            $one = random_int(0, 2047);
            $two = random_int(0, 2047);
            $code = random_int(0, 99);
        } else {
            $x = 0;
            $generation = [];
            while ($x < $entropy) {
                $generation[$x] = random_int(0, 2047);
                $x++;
            }

            $response = NULL;
            foreach ($generation as $item) {
                $response = $response . $dictionary[$item] . ' ' ;
            }

            return (string) trim($response);
        }
        $verify_prefix = null;
        if(!$no_prefix){
            $verify_prefix = Config::get('tokenpass.sig_verify_prefix');
            if($verify_prefix){
                $verify_prefix .= ' ';
            }
        }
        return (string) $verify_prefix.$dictionary[$one]. ' ' .$dictionary[$two]. ' ' .$code;
    }
    
    
    public static function getDisabledTokens($user_id)
    {
        $get = UserMeta::getMeta($user_id, 'disabled_tokens');
        $decode = json_decode($get, true);
        if(!$get OR !is_array($decode)){
            return array();
        }
        return $decode;
    }

    // ------------------------------------------------------------------------
    // XChain Sync
    
    public function syncWithXChain() {
        // never sync pseudo addresses
        if ($this->isPseudoAddress()) {
            return;
        }

        $xchain = app('Tokenly\XChainClient\Client');

        if (!$this['xchain_address_id']) {
            EventLog::log('xchain.addressSync', ['id' => $this['id'], 'address' => $this['address'],]);

            $update_vars = [];

            // create a xchain unmanaged address
            try {
                $result = $xchain->newUnmanagedPaymentAddress($this['address']);
                $xchain_address_id = $result['id'];
                $update_vars['xchain_address_id'] = $xchain_address_id;
            } catch (Exception $e) {
                EventLog::logError('xchain.addressSync.failed', $e, ['id' => $this['id'], 'address' => $this['address'], 'xchain_address_id' => $xchain_address_id,]);
                throw $e;
            }

            // create an xchain send monitor
            $webhook_endpoint = route('xchain.receive');
            if(env('XCHAIN_CALLBACK_USE_NONCE') == 'true'){
                $webhook_endpoint .= '?nonce='.env('XCHAIN_CALLBACK_NONCE');
            }

            try {
                $result = $xchain->newAddressMonitor($this['address'], $webhook_endpoint, 'send', true);
                $send_monitor_id = $result['id'];
                $update_vars['send_monitor_id'] = $send_monitor_id;
            } catch (Exception $e) {
                EventLog::logError('xchain.addressSync.failed', $e, ['id' => $this['id'], 'address' => $this['address'], 'send_monitor_id' => $send_monitor_id,]);
                throw $e;
            }

            // create an xchain receive monitor
            try {
                $result = $xchain->newAddressMonitor($this['address'], $webhook_endpoint, 'receive', true);
                $receive_monitor_id = $result['id'];
                $update_vars['receive_monitor_id'] = $receive_monitor_id;
            } catch (Exception $e) {
                EventLog::logError('xchain.addressSync.failed', $e, ['id' => $this['id'], 'address' => $this['address'], 'receive_monitor_id' => $receive_monitor_id,]);
                throw $e;
            }

            // update the address with the new data
            $address_repository = app('Tokenpass\Repositories\AddressRepository');
            $address_repository->update($this, $update_vars);
        }

        // always sync the balances with XChain, even if the address isn't new
        $this->syncAccountBalancesWithXChain();
        
        //invalidate any loans/promises they don't have correct balance for
        $this->invalidateOverdrawnPromises();
    }

    public function syncAccountBalancesWithXChain() {
        $xchain = app('Tokenly\XChainClient\Client');
        $balances = $xchain->getAccountBalances($this['xchain_address_id'], 'default', 'confirmed');
        self::updateAddressBalancesTable($this, $this->balancesToSatoshis($balances));
    }

    // ------------------------------------------------------------------------
    
    protected function balancesToSatoshis($balances_float) {
        $balances_sat = [];
        foreach($balances_float as $asset => $float_balance) {
            $balances_sat[$asset] = CurrencyUtil::valueToSatoshis($float_balance);
        }
        return $balances_sat;
    }

    protected static function updateAddressBalancesTable($address, $balance_list) {

        $current = DB::table('address_balances')->where('address_id', '=', $address->id)->get();
        $stamp = date('Y-m-d H:i:s');
        foreach($balance_list as $asset => $balance){
            $found = false;
            foreach($current as $row){
                if($row->asset == $asset){
                    $found = $row;
                    break;
                }
            }
            if($found){
                //update balance entry
                DB::table('address_balances')->where('id', $found->id)->update(array('balance' => $balance, 'updated_at' => $stamp));
            }
            else{
                //new balance entry
                DB::Table('address_balances')->insert(array('address_id' => $address->id, 'asset' => $asset,
                                                            'balance' => $balance, 'updated_at' => $stamp));
            }
        }
        return true;
    }
    
    public function user()
    {
        return $this->getUser();
    }

    public function getUser()
    {
        return User::find($this->user_id);
    }

    public function balances()
    {
        $get = Address::getAddressBalances($this->id, false, false);
        if(!$get){
            return array();
        }
        return $get;
    }
    
    public function promises()
    {
        return Address::getPromiseBalances($this->id);
    }
    
    public function getPromiseBalances($addressId)
    {
        $address = Address::find($addressId);
        if(!$address){
            return false;
        }
        $get = DB::table('provisional_tca_txs')->where('destination', $address->address)->get();
        if(!$get){
            return array();
        }
        return $get;
    }
    
	public static function extractSignature($text,$start = '-----BEGIN BITCOIN SIGNATURE-----', $end = '-----END BITCOIN SIGNATURE-----')
	{
		$inputMessage = trim($text);
		if(strpos($inputMessage, $start) !== false){
			//pgp style signed message format, extract the actual signature from it
			$expMsg = explode("\n", $inputMessage);
			foreach($expMsg as $k => $line){
				if($line == $end){
					if(isset($expMsg[$k-1])){
						$inputMessage = trim($expMsg[$k-1]);
					}
				}
			}
		}
		return $inputMessage;
	}
    
    public static function checkUser2FAEnabled($user)
    {
        if($user->second_factor == 0){
            return false;
        }
        $count = Address::where('user_id', $user->id)->where('second_factor_toggle', 1)->where('verified', 1)->count();
        if(!$count OR $count == 0){
            return false;
        }
        return true;
    }
    
    public function invalidateOverdrawnPromises()
    {
        $promises = Provisional::where('source', $this->address)->get();
        $balances = $this->balances();
        if($promises AND $balances){
            $promise_totals = array();
            foreach($promises as $promise){
                if(!isset($promise_totals[$promise->asset])){
                    $promise_totals[$promise->asset] = 0;
                }
                $new_total = $promise_totals[$promise->asset] + $promise->quantity;
                if(!isset($balances[$promise->asset]) OR $new_total > $balances[$promise->asset]){
                    $promise->invalidate();
                    continue;
                }
                $promise_totals[$promise->asset] = $new_total;
            }
        }
    }
    
    public static function getUserTokenBalance($user, $token)
    {
        $address_list = Address::getAddressList($user->id, null, true);
        if(!$address_list OR count($address_list) == 0){
            return false;
        }
        $balance = 0;
        $address_ids = array();
        $addresses = array();
        foreach($address_list as $address){
            $address_ids[] = $address->id;
            $addresses[] = $address->address;
        }
        $balance_list = DB::table('address_balances')->where('asset', $token)->whereIn('address_id', $address_ids)->get();
        if($balance_list){
            foreach($balance_list as $item){
                $balance += $item->balance;
            }
        }
        //subtract promises
        $promise_list = Provisional::where('asset', $token)->whereIn('source', $addresses)->get();
        if($promise_list){
            foreach($promise_list as $promise){
                $balance -= $promise->quantity;
            }
        }
        return $balance;
    }

    public function setUpPayToVerifyMethod() {
        $xchain = app('Tokenly\XChainClient\Client');

        //Get pay-to-verify address
        $verify_address = $xchain->newPaymentAddress();
        $update_vars['verify_address'] = $verify_address['address'];
        $update_vars['verify_address_uuid'] = $verify_address['id'];

        $address = $verify_address['address'];
        // Monitor payment

        $webhook_endpoint = route('inventory.pockets.verify_payment');
        $monitor = $xchain->newAddressMonitor($address, $webhook_endpoint);
        $update_vars['verify_monitor_uuid'] = $monitor['id'];

        // update address with the new data
        $address_repository = app('Tokenpass\Repositories\AddressRepository');
        $address_repository->update($this, $update_vars);
    }

    public function sendTransactionEmail($payload){
        $transactionTime = date('Y-m-d H:i', strtotime($payload['transactionTime']));
        $transactionId = $payload['txid'];
        $user = User::find($this->user_id);

        // IF notification was already sent, leave
        if(TxMailNotification::where('userId', $user->id)->where('txid', $transactionId)->exists()) {
            return;
        }

        $asset = $payload['asset'];
        $amount = $payload['quantity'];
        $input_addresses = $payload['sources'];
        $output_addresses = $payload['destinations'];
        $data = array('user' => $user, 'transactionTime' => $transactionTime, 'asset' => $asset, 'amount' => $amount,
                      'input_addresses' => $input_addresses, 'output_addresses' => $output_addresses,
                      'transactionId' => $transactionId);
        $user->notify('emails.tx.received-tx', 'New received transaction', $data);

        if (!Mail::failures()) {
            $tx_mail_notification = new TxMailNotification;
            $tx_mail_notification->txid = $transactionId;
            $tx_mail_notification->userId = $user->id;
            $tx_mail_notification->save();
        } else {
            //TODO: Log error
        }
    }
}

