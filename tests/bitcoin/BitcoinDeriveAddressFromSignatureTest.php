<?php

use Tokenpass\Util\BitcoinUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* BitcoinDeriveAddressFromSignatureTest
*/
class BitcoinDeriveAddressFromSignatureTest extends TestCase {

    protected $use_database = false;

    public function testDeriveAddressFromSignatureTest() {
        $signature = 'IMv2AycectvhDxuVyCMnFUHIHy74hHHuxd2NQILoiuYgKJQiOji4tEFcRw9mWYQuoSHjXkN/SQWgyQdwTVXuheI=';
        $expected_address = '12iVwKP7jCPnuYy7jbAbyXnZ3FxvgLwvGK';
        $message = 'Hello World';

        $actual_address = BitcoinUtil::deriveAddressFromSignature($signature, $message);
        PHPUnit::assertEquals($expected_address, $actual_address);
    }



    ////////////////////////////////////////////////////////////////////////



}
