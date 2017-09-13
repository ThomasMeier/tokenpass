<?php
namespace Tokenpass\Civic;

class JWT {

    static function createToken ($issuer, $audience, $subject, $expiresIn, $payload, $prvKeyHex) {
        $now = time();
        $until = strtotime('+3 minutes', time());

        $content = array(
            'jti' => \Ramsey\Uuid\Uuid::uuid4(),
            'iat' => $now,
            'exp' => $until,
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => $subject,
            'data' => $payload
        );

        $header = array(
            'alg' => 'ES256',
            'typ' => 'JWT'
        );
        $headers = json_encode($header);
        $content = json_encode($content);

        $jws = new \Gamegos\JWS\JWS();
        return $jws->encode($headers, $content , $prvKeyHex);
    }

    static function createCivicExt($body, $clientAccessSecret) {
        $bodyStr = json_encode($body);
        $hmacBuffer = hash_hmac('sha256', $bodyStr, $clientAccessSecret);
        return base64_encode($hmacBuffer);
    }
}