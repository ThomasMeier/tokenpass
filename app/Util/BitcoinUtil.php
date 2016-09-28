<?php 

namespace Tokenpass\Util;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcSerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\CompactSignatureSerializerInterface;
use BitWasp\Bitcoin\MessageSigner\MessageSigner;
use BitWasp\Buffertools\Buffer;
use Exception;
use LinusU\Bitcoin\AddressValidator;

/**
* Bitcoin Utilities
*/
class BitcoinUtil
{
    
    public static function deriveAddressFromSignature($signature, $message) {

        $ec             = Bitcoin::getEcAdapter();
        $cs             = EcSerializer::getSerializer($ec, CompactSignatureSerializerInterface::class);
        $message_signer = new MessageSigner($ec);
        $sig_buffer     = new Buffer(base64_decode($signature));
        $compact_sig    = $cs->parse($sig_buffer);
        $message_hash   = $message_signer->calculateMessageHash($message);

        try {
            $pubkey = $ec->recover($message_hash, $compact_sig);
        } catch (Exception $e) {
            throw new Exception("unable to recover public key", 0, $e);
        }

        $address = AddressFactory::fromKey($pubkey);
        return $address->getAddress();
    }

    public static function isValidBitcoinAddress($address) {
        return AddressValidator::isValid($address);
    }

}