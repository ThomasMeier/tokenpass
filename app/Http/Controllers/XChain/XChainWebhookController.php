<?php 

namespace Tokenpass\Http\Controllers\XChain;

use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Input;
use Mockery\Exception;
use Tokenpass\Http\Controllers\Controller;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenly\XChainClient\WebHookReceiver;
use Tokenpass\Models\Address;

class XChainWebhookController extends Controller {

    public function receive(WebHookReceiver $webhook_receiver, Request $request) {
        
        $use_nonce = env('XCHAIN_CALLBACK_USE_NONCE');
        if($use_nonce == 'true'){
            $env_nonce = env('XCHAIN_CALLBACK_NONCE');
            $nonce = Input::get('nonce');
            if($nonce != $env_nonce){
                return false;
            }
        }
        try {
            $data = $webhook_receiver->validateAndParseWebhookNotificationFromRequest($request);
            $payload = $data['payload'];

            // check block, receive or send
            $this->handleXChainPayload($payload);

        } catch (Exception $e) {
            EventLog::logError('webhook.error', $e);
            if ($e instanceof HttpResponseException) { throw $e; }
            throw new HttpResponseException(new Response("An error occurred"), 500);
        }

        return 'ok';
    }

    // ------------------------------------------------------------------------
    
    protected function handleXChainPayload($payload) {
        switch ($payload['event']) {
            case 'block':
                // new block event
                app('Tokenpass\Handlers\XChain\XChainBlockHandler')->handleBlock($payload);
                break;

            case 'send':
            case 'receive':
                // new send or receive event
                app('Tokenpass\Handlers\XChain\XChainTransactionHandler')->handleTransaction($payload);
                break;

            case 'invalidation':
                // new invalidation event
                EventLog::log('event.invalidation', $payload);
                break;

            default:
                EventLog::log('event.unknown', "Unknown event type: {$payload['event']}");
        }
    }


    //Verify address through payment

    public function receiveVerifyPayment(WebHookReceiver $webhook_receiver, \Illuminate\Http\Request $request) {

        try {
            $data = $webhook_receiver->validateAndParseWebhookNotificationFromRequest($request);

            $payload = $data['payload'];

            // check block, receive or send
            $address = Address::whereIn('verify_address', $payload['destinations'])->whereIn('address', $payload['sources'])->get()->first();
            if(!empty($address)) {
                $address->verified = 1;
                $address->save();
            }
        } catch (\Exception $e) {
            EventLog::logError('webhook.error', $e);
            if ($e instanceof HttpResponseException) { throw $e; }
            throw new HttpResponseException(new \Illuminate\Http\Response("An error occurred"), 500);
        }
    }

}
