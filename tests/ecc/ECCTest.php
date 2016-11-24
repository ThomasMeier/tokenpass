<?php

use Tokenpass\Util\ECCUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

class ECCTest extends TestCase
{

    protected $use_database = false;
    
    public function testECCFunctions() {
        $enc_private_key = ECCUtil::generateEncodedPrivateKey();
        PHPUnit::assertNotEmpty($enc_private_key);

        $enc_public_key = ECCUtil::getEncodedPublicKey($enc_private_key);
        PHPUnit::assertNotEmpty($enc_public_key);

        $document = "Hello there world\n";
        $enc_signature = ECCUtil::sign($document, $enc_private_key);
        PHPUnit::assertNotEmpty($enc_signature);

        $is_valid = ECCUtil::verify($document, $enc_signature, $enc_public_key);
        PHPUnit::assertTrue($is_valid);

        $is_valid = ECCUtil::verify("Another document", $enc_signature, $enc_public_key);
        PHPUnit::assertFalse($is_valid);
    } 
}
