<?php

class JWT {


    function createToken ($issuer, $audience, $subject, $expiresIn, $payload, $prvKeyHex) {
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
        $header = json_encode($header);
        $sContent = json_encode($content);
    }
}