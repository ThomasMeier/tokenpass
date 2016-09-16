<?php

return array(
    'sig_verify_prefix' =>  env('TOKENPASS_SIG_VERIFY_PREFIX', 'TOKENPASS'),
    'crypto_verify_code_expire' => 3600,
    'instant_verify_code_expire' => 600,
    'supported_bvam_labels' => array(
        'expiration' => array('label' => 'Expires', 'type' => 'date'),
        'website' => array('label' => 'Website', 'type' => 'link'),
        'owner' => array('label' => 'Issuer', 'type' => 'text'),
    ),
);
