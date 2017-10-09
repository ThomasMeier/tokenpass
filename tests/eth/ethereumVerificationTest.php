<?php

use Tokenpass\Util\EthereumUtil;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use JsonRPC\Client;

class ethereumVerificationTest extends TestCase
{

    protected $use_database = true;

    private function sendTestTx() {
        $rpcClient = new Client($_ENV['ETH_RPC_SERVER']);
        $_ENV['ETH_TEST_USER_ADDR'];
        $txOptions = ['from' => $_ENV['ETH_TEST_USER_ADDR'],
                      'to' => $_ENV['ETH_TOKENLY_ADDR'],
                      'value' => "0x1D6EF8561880"];
        $rpcClient->execute('eth_sendTransaction', [$txOptions]);
    }

    public function testCheckVerification()
    {
        $this->sendTestTx();
        $eth = new EthereumUtil();
        $results = $eth->searchBlocks($_ENV['ETH_TEST_USER_ADDR'], "0x1D6EF8561880");
        var_dump($results);
        $this->assertTrue($results);
    }

    // Assuming test user balance is nonzero
    // As in real world the testnet reflects users having nonzero balances
    public function testCheckBalance() {
        $eth = new EthereumUtil();
        $bal = $eth->checkBalance($_ENV['ETH_TEST_USER_ADDR']);
        $this->assertFalse(empty(EthereumUtil::hexdec_0x($bal)));
    }

    public function updateAddress() {
        $this->assertTrue(true);
    }

    public function balanceSync() {
        $this->assertTrue(true);
    }
}
