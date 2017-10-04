<?php

use Tokenpass\Util\EthereumUtil;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ethereumVerificationTest extends TestCase
{

    protected $use_database = true;

    public function testCreateFilter()
    {
        $eth = new EthereumUtil();
        $filterId = $eth->createNewFilter($_ENV['ETH_TEST_USER_ADDR']);
        $this->assertLessThan(strlen($filterId), 20);
    }

    public function testCheckFilter()
    {
        $eth = new EthereumUtil();
        $filterId = $eth->createNewFilter($_ENV['ETH_TEST_USER_ADDR']);
        $filterResults = $eth->checkFilter($filterId);
        $this->assertTrue(is_array($filterResults));
    }

    public function testCheckBalance() {
        $eth = new EthereumUtil();
        $bal = $eth->checkBalance('0x1d93D28411E4aF5e8a527719A0f55449D6EeA0d6');
        var_dump($bal);
        $this->assertTrue(true);
    }

    public function updateAddress() {
        $this->assertTrue(true);
    }

    public function balanceSync() {
        $this->assertTrue(true);
    }
}
