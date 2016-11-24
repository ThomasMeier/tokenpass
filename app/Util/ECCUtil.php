<?php 

namespace Tokenpass\Util;

use Exception;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Random\RandomGeneratorFactory;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;

/**
* ECC Utilities
*/
class ECCUtil
{

    public static function generateEncodedPrivateKey() {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $private_key = $generator->createPrivateKey();

        $serializer = new DerPrivateKeySerializer($adapter);
        return base64_encode($serializer->serialize($private_key));
    }

    public static function getEncodedPublicKey($base64_private_key) {
        $adapter = EccFactory::getAdapter();
        $private_key_serializer = new DerPrivateKeySerializer($adapter);
        $private_key = $private_key_serializer->parse(base64_decode($base64_private_key));

        $public_key = $private_key->getPublicKey();
        $public_key_serializer = new DerPublicKeySerializer($adapter);
        return base64_encode($public_key_serializer->serialize($public_key));
    }

    public static function sign($document, $base64_private_key) {
        $algorithm = 'sha256';
        $adapter = EccFactory::getAdapter();

        $private_key_serializer = new DerPrivateKeySerializer($adapter);
        $private_key = $private_key_serializer->parse(base64_decode($base64_private_key));

        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();

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
    
        $public_key_serializer = new DerPublicKeySerializer($adapter);
        $public_key = $public_key_serializer->parse(base64_decode($base64_public_key));

        $serializer = new DerSignatureSerializer();
        $signature = $serializer->parse(base64_decode($base64_signature));
        $hash = $signer->hashData($generator, $algorithm, $document);
        return $signer->verify($public_key, $signature, $hash);
    }

}