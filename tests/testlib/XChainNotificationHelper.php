<?php

use Illuminate\Http\Request;
use Mockery as m;

class XChainNotificationHelper {

    protected $web_hook_receiver_is_mocked = null;

    function __construct() {
    }

    public function receiveNotificationWithWebhookController($notification_data) {
        // create($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
        if (!$this->web_hook_receiver_is_mocked) {

            $mock_webhook_receiver = m::mock('Tokenly\XChainClient\WebHookReceiver')->makePartial();
            $mock_webhook_receiver->shouldReceive('validateWebhookNotification')->andReturn(true);
            app()->bind('Tokenly\XChainClient\WebHookReceiver', function ($app) use ($mock_webhook_receiver) {
                return $mock_webhook_receiver;
            });

            $this->web_hook_receiver_is_mocked = true;
        }


        $content = ['payload' => json_encode($notification_data)];
        $request = Request::create('http://localhost/_xchain_client_receive', 'POST', [], [], [], ['Content-Type' => 'application/json'], json_encode($content));

        $controller = app('Tokenpass\Http\Controllers\XChain\XChainWebhookController');
        $controller->receive(app('Tokenly\XChainClient\WebHookReceiver'), $request);
    }

    public function sampleSendNotificationForAddress($address, $override_vars=[]) {
        $override_vars = array_merge([
            'notifiedAddress'   => $address['address'],
            'notifiedAddressId' => $address['send_monitor_id'],
        ], $override_vars);
        return $this->sampleSendNotification($override_vars);
    }

    public function sampleSendNotification($override_vars=[]) {
        $override_vars = array_merge([
            "event"        => "send",
            "sources"      => ["1GHRfqhgC66E8dq2iDMwQsJVWegHV8s2ki"],
            "destinations" => ["1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD"],
        ], $override_vars);

        return $this->sampleReceiveNotification($override_vars);
    }

    public function sampleReceiveNotificationForAddress($address, $override_vars=[]) {
        $override_vars = array_merge([
            'notifiedAddress'   => $address['address'],
            'notifiedAddressId' => $address['receive_monitor_id'],
            'destinations'      => [$address['address']],
        ], $override_vars);
        return $this->sampleReceiveNotification($override_vars);
    }

    public function sampleBlockNotification($override_vars=[]) {
        $_json = <<<'EOT'
        {
            "notificationId": 10001,
            "event": "block",
            "network": "bitcoin",

            "bits": "181b7b74",
            "chainwork": "0000000000000000000000000000000000000000000326f8ea33036afa4eb816",
            "confirmations": 1,
            "difficulty": 40007470271.27126,
            "hash": "000000000000000015f697b296584d9d443d2225c67df9033157a9efe4a8faa0",
            "height": 332913,
            "isMainChain": true,
            "merkleroot": "7f363b95ee09c5e77c3d8da2dcd67ff5616e478774286b399c80d2b8cd865e9e",
            "nonce": 3289248220,
            "poolInfo": {
                "poolName": "KNCminer",
                "url": "https://www.kncminer.com/"
            },
            "previousblockhash": "000000000000000007ecb6e897b512134dad930fa9298b706bf21042c65d34b2",
            "reward": 25,
            "size": 749143,
            "time": 1417742460,
            "tx": [
                "f88d98717dacb985e3ad49ffa66b8562d8194f1885f58425e1c8582ce2ac5b58",
                "8de3c8666c40f73ae13df0206e9caf83c075c51eb54349331aeeba130b7520c8",
                "8a26b51174d5d50e99f01cec0868572bc3fb6f52df3e7ce75421b26cc4050687",
                "30208222840c83db7b67a1d178ac6d8578b0ea66d09bcef7842923927ade5f7b",
                "826c9556b12c501d268daf8f36a41eade54e90c22d91a7f33a71cad7d15ae530",
                "e46bf3e9ba7ffa0722a5d153373fd1c4499285537b73d8842d695267955d68ec",
                "71504730cef7e0c33a6462fe8497f18a02e33ea1b6623c253fcae30658a96b3b"
           ],
            "version": 2
        }
EOT;
        $out = json_decode($_json, true);
        $out = array_replace_recursive($out, $override_vars);
        return $out;
    }


    public function sampleReceiveNotification($override_vars=[]) {
        $_json = <<<'EOT'
        {
            "asset": "BITCRYSTALS",
            "bitcoinTx": {
                "blockhash": "000000000000000004bcf1f94625daa8985ea22c984f835ff19da43f7f037455",
                "blockheight": 378708,
                "blocktime": 1444746439,
                "fees": 0.0001,
                "locktime": 0,
                "size": 708,
                "txid": "28ea1471fbba842ed8d4a9ec48e328feab6abe30bbeaeea52bfdcac6419095a2",
                "valueIn": 0.00023826,
                "valueOut": 0.00013826,
                "version": 1,
                "vin": [
                    {
                        "addr": "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg",
                        "doubleSpentTxID": null,
                        "n": 0,
                        "scriptSig": {
                            "asm": "30450221009b3ad32c85e019103533d4cbffd863e51ad39c6094bc8512b6d6149b36071d260220323c79c9ef3415bc977dd8bd9a503f1c62ac94b33af70e9cc8eab95396ffadfc01 0249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9",
                            "hex": "4830450221009b3ad32c85e019103533d4cbffd863e51ad39c6094bc8512b6d6149b36071d260220323c79c9ef3415bc977dd8bd9a503f1c62ac94b33af70e9cc8eab95396ffadfc01210249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9"
                        },
                        "sequence": 4294967295,
                        "txid": "abd52b96651717c810d941ae682a373efb508bca9a88c0875065686d748f9496",
                        "value": 7.496e-05,
                        "valueSat": 7496,
                        "vout": 2
                    },
                    {
                        "addr": "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg",
                        "doubleSpentTxID": null,
                        "n": 1,
                        "scriptSig": {
                            "asm": "3045022100ad14b9877667f6ea1e1fd5582954365260885a4c6208a5ceb581563d90bc1e0402207350ad7d6a46c9b5f332dc4932fcd020b89f63de6c4699ed4197cf078e86518801 0249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9",
                            "hex": "483045022100ad14b9877667f6ea1e1fd5582954365260885a4c6208a5ceb581563d90bc1e0402207350ad7d6a46c9b5f332dc4932fcd020b89f63de6c4699ed4197cf078e86518801210249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9"
                        },
                        "sequence": 4294967295,
                        "txid": "7b13cd4b75274b871095f0efbf987a683b8bdaf93e0c6bd0291fa3b3c3e8299a",
                        "value": 5.47e-05,
                        "valueSat": 5470,
                        "vout": 0
                    },
                    {
                        "addr": "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg",
                        "doubleSpentTxID": null,
                        "n": 2,
                        "scriptSig": {
                            "asm": "3044022047ba41a514f53d7fc7a1b1479ff7838d1d940eca965669510b664a0124e74a3b02205da1f7f8767633ac4e0bf883f459906fb4041da102e397f369d67d0b78a4e45e01 0249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9",
                            "hex": "473044022047ba41a514f53d7fc7a1b1479ff7838d1d940eca965669510b664a0124e74a3b02205da1f7f8767633ac4e0bf883f459906fb4041da102e397f369d67d0b78a4e45e01210249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9"
                        },
                        "sequence": 4294967295,
                        "txid": "bc945c8c171ea871ddf46e562fc133b3e8457acf88a07416a434700ca104f27b",
                        "value": 5.43e-05,
                        "valueSat": 5430,
                        "vout": 0
                    },
                    {
                        "addr": "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg",
                        "doubleSpentTxID": null,
                        "n": 3,
                        "scriptSig": {
                            "asm": "3045022100e1eb2329f941f92e06689cac03ae872fa7867c09099e7db61d51cec03fcaa60c02200ad62301c0437a5d07444222a38fc5807f067bb0de8a17a90a9f583d204af28401 0249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9",
                            "hex": "483045022100e1eb2329f941f92e06689cac03ae872fa7867c09099e7db61d51cec03fcaa60c02200ad62301c0437a5d07444222a38fc5807f067bb0de8a17a90a9f583d204af28401210249b29477f49713540302baf27a78f2db14d95f4b14682a857f7bef6d8aee73b9"
                        },
                        "sequence": 4294967295,
                        "txid": "ff19603b953761c6116d7a7564007127dd2776b412cd5bc30a419523ad1454e9",
                        "value": 5.43e-05,
                        "valueSat": 5430,
                        "vout": 0
                    }
                ],
                "vout": [
                    {
                        "n": 0,
                        "scriptPubKey": {
                            "addresses": [
                                "1GHRfqhgC66E8dq2iDMwQsJVWegHV8s2ki"
                            ],
                            "asm": "OP_DUP OP_HASH160 a7a522a51998150ee19be3de15d8dacc11ee672c OP_EQUALVERIFY OP_CHECKSIG",
                            "hex": "76a914a7a522a51998150ee19be3de15d8dacc11ee672c88ac",
                            "reqSigs": 1,
                            "type": "pubkeyhash"
                        },
                        "value": "0.00005470"
                    },
                    {
                        "n": 1,
                        "scriptPubKey": {
                            "asm": "OP_RETURN 01b55e4a754de08b031b5d2a9387eaa1d825a0089c9faac701baaf71",
                            "hex": "6a1c01b55e4a754de08b031b5d2a9387eaa1d825a0089c9faac701baaf71",
                            "type": "nulldata"
                        },
                        "value": "0.00000000"
                    },
                    {
                        "n": 2,
                        "scriptPubKey": {
                            "addresses": [
                                "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg"
                            ],
                            "asm": "OP_DUP OP_HASH160 f3644d0d403b9fb238d8b0889b6ab499923f4ae6 OP_EQUALVERIFY OP_CHECKSIG",
                            "hex": "76a914f3644d0d403b9fb238d8b0889b6ab499923f4ae688ac",
                            "reqSigs": 1,
                            "type": "pubkeyhash"
                        },
                        "value": "0.00008356"
                    }
                ]
            },
            "blockSeq": 816,
            "confirmationTime": "2015-10-13T15:21:04+0000",
            "confirmations": 6,
            "confirmed": true,
            "counterpartyTx": {
                "asset": "BITCRYSTALS",
                "destinations": [
                    "1GHRfqhgC66E8dq2iDMwQsJVWegHV8s2ki"
                ],
                "dustSize": 5.47e-05,
                "dustSizeSat": 5470,
                "quantity": 480,
                "quantitySat": 48000000000,
                "sources": [
                    "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg"
                ],
                "type": "send",
                "validated": true
            },
            "destinations": [
                "1GHRfqhgC66E8dq2iDMwQsJVWegHV8s2ki"
            ],
            "event": "receive",
            "network": "counterparty",
            "notificationId": "106a03e7-3a1a-4226-9636-15a7ad592815",
            "notifiedAddress": "1GHRfqhgC66E8dq2iDMwQsJVWegHV8s2ki",
            "notifiedAddressId": "e06d0538-89b0-4222-a24c-bfe498b539f7",
            "quantity": 480,
            "quantitySat": 48000000000,
            "sources": [
                "1PBwMefEdUExqcVtLovg64vzvX7BU8TSDg"
            ],
            "transactionTime": "2015-10-13T12:54:50+0000",
            "txid": "28ea1471fbba842ed8d4a9ec48e328feab6abe30bbeaeea52bfdcac6419095a2",
            "transactionFingerprint": "ffff0000000000000000000000000000000000000000000000000000aaaaaaaa"
        }

EOT;
        $out = json_decode($_json, true);

        $out = array_replace_recursive($out, $override_vars);

        return $out;
    }




}
