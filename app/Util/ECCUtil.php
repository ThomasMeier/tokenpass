<?php 

namespace Tokenpass\Util;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcAdapterFactory;
use BitWasp\Buffertools\Buffer;
use Exception;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Random\RandomGeneratorFactory;
use Mdanter\Ecc\Serializer\Point\CompressedPointSerializer;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;

/**
* ECC Utilities
*/
class ECCUtil
{

    public static function padHex($data) {
        return ((strlen($data) % 2) ? '0' : '').$data;
    }

    public static function generateEncodedPrivateKey() {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $private_key = $generator->createPrivateKey();
        return base64_encode(hex2bin(self::padHex(gmp_strval($private_key->getSecret(), 16))));
    }

    public static function getEncodedPublicKey($base64_private_key) {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $key_gmp_val = gmp_init(bin2hex(base64_decode($base64_private_key)), 16);
        $private_key = $generator->getPrivateKeyFrom($key_gmp_val);

        $public_key = $private_key->getPublicKey();
        $point = $public_key->getPoint();
        $compressed_serializer = new CompressedPointSerializer($adapter);
        $compressed_hex_string = $compressed_serializer->serialize($public_key->getPoint());
        return base64_encode(hex2bin(self::padHex($compressed_hex_string)));
    }

    public static function sign($document, $base64_private_key) {
        $algorithm = 'sha256';
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();

        $key_gmp_val = gmp_init(bin2hex(base64_decode($base64_private_key)), 16);
        $private_key = $generator->getPrivateKeyFrom($key_gmp_val);

        $signer = new Signer($adapter);
        $hash = $signer->hashData($generator, $algorithm, $document);
        $random = RandomGeneratorFactory::getRandomGenerator();
        $random_k = $random->generate($generator->getOrder());
        $signature = $signer->sign($private_key, $hash, $random_k);

        $serializer = new DerSignatureSerializer();
        $serialized_sig = $serializer->serialize($signature);
        return base64_encode($serialized_sig);
    }

    public static function verify($document, $base64_signature, $base64_public_key) {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $signer = new Signer($adapter);
        $algorithm = 'sha256';

        $public_key_buffer = new Buffer(base64_decode($base64_public_key));
        $ec_adapter = EcAdapterFactory::getAdapter(Bitcoin::getMath(), $generator);
        $public_key = $ec_adapter->publicKeyFromBuffer($public_key_buffer);

        $serializer = new DerSignatureSerializer();
        $signature = $serializer->parse(base64_decode($base64_signature));
        $hash = $signer->hashData($generator, $algorithm, $document);
        return $signer->verify($public_key, $signature, $hash);
    }

}