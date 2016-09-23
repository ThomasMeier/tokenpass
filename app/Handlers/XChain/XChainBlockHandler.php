<?php

namespace Tokenpass\Handlers\XChain;

use Exception;
use Tokenly\LaravelEventLog\Facade\EventLog;

/**
 * This is invoked when a new block is received
 */
class XChainBlockHandler {

    public function __construct() {
    }

    public function handleBlock($payload) {
        EventLog::log('block.received', $payload, ['network', 'height', 'hash']);
        // don't do anything with a block
    }

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    // Checks
    
}
