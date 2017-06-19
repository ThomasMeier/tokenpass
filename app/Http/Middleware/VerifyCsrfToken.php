<?php

namespace Tokenpass\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
        '/inventory/asset/*',
        '/inventory/address/*',
        '/auth/bitcoin',
        '/auth/signed',
        '/_xchain_client_receive',
        '/_xchain_client_receive_verify_payment',
    ];
}
