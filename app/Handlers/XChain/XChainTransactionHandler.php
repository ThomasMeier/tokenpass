<?php

namespace Tokenpass\Handlers\XChain;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\XChainClient\Client as XChainClient;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Models\Address;
use Tokenpass\Repositories\AddressRepository;

/**
 * This is invoked when a new block is received
 */
class XChainTransactionHandler {

    const MINIMUM_CONFIRMATIONS = 2;

    public function __construct(AddressRepository $address_repository, XChainClient $xchain_client) {
        $this->address_repository = $address_repository;
        $this->xchain_client      = $xchain_client;
    }

    public function handleTransaction($payload) {
        // Log::debug("\$payload=".json_encode($payload, 192));
        //EventLog::log('transaction.'.$payload['event'].'.received', $payload, ['network', 'confirmations', 'quantity', 'asset', 'sources', 'destinations']);

        switch ($payload['event']) {
            case 'send': $is_send = true; break;
            case 'receive': $is_send = false; break;
            default: throw new Exception("Unknown event type: {$payload['event']}", 1);
        }

        // find the address
        if ($is_send) {
            $address = $this->address_repository->findBySendMonitorID($payload['notifiedAddressId']);
        } else {
            $address = $this->address_repository->findByReceiveMonitorID($payload['notifiedAddressId']);
        }
        if (!$address) {
            EventLog::warning('address.notFound', $payload, ['network', 'event', 'notifiedAddressId']);
            return;
        }
        
        //see if this is an existing provisional tx 
        $find_prov_tx = DB::table('provisional_tca_txs')->where('fingerprint', $payload['transactionFingerprint'])->orWhere('txid', $payload['txid'])->first();        

        // sync new balance from xchain if this has 2 or more confirmations
        if ($payload['confirmations'] >= self::MINIMUM_CONFIRMATIONS) {
            try {
                // sync balances
                EventLog::log('address.sync', $address, ['id','address']);
                $address->syncWithXChain();

                // fire an address balanced changed event
                Event::fire(new AddressBalanceChanged($address));
            } catch (Exception $e) {
                $error_data = [];
                EventLog::logError('address.sync.failed', $e, ['id' => $address['id'], 'address' => $address['address']]);
            }

            if($find_prov_tx){
                //remove provisional tx from system
                DB::table('provisional_tca_txs')->where('id', $find_prov_tx->id)->delete();
            }
            var_dump($address);
            if(!$is_send && $address->notify_email) {
                $address->sendTransactionEmail($payload);
            }
        }
        else{
            //check to see if this is coming from a provisional TCA source address
            if(!$find_prov_tx AND !$is_send){ //if provisional tx not already added
                //get list of source addresses
                $payload_addresses = $payload['sources'];

                //look for matches
                $find_provisional = DB::table('provisional_tca_addresses')
                                    ->whereIn('address', $payload_addresses)->get();
                if($find_provisional){
                    $valid_provisional = false;
                    $provisional_client = null;
                    foreach($find_provisional as $prov_address){
                        //make sure asset is valid
                        if($prov_address->assets == null){
                            $valid_provisional = true;
                        }
                        else{
                            $assets = json_decode($prov_address->assets, true);
                            if(in_array($payload['asset'], $assets)){
                                $valid_provisional = true;
                            }
                        }
                        $provisional_client = $prov_address->client_id;
                    }
                    if($valid_provisional){
                        //add provisional tx
                        $time = date('Y-m-d H:i:s');
                        $tx_data = array();
                        $tx_data['source'] = $payload_addresses[0];
                        $tx_data['destination'] = $payload['destinations'][0];
                        $tx_data['asset'] = $payload['asset'];
                        $tx_data['fingerprint'] = $payload['transactionFingerprint'];
                        $tx_data['txid'] = $payload['txid'];
                        $tx_data['quantity'] = $payload['quantitySat'];
                        $tx_data['created_at'] = $time;
                        $tx_data['updated_at'] = $time;
                        $tx_data['client_id'] = $provisional_client;
                        $insert = DB::table('provisional_tca_txs')->insert($tx_data);
                        EventLog::log('transaction.provisional.received', $tx_data);
                    }
                }
            }
        }

        return;
    }

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    // Checks
    
}
