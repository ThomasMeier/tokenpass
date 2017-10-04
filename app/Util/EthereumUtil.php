<?php

namespace Tokenpass\Util;

use JsonRPC\Client;

/**
 * Ethereum Util
 *
 */
class EthereumUtil {

    protected $rpcClient;

    protected $tokenlyAddress;

    protected $currentBlock;

    public function __construct() {
        $this->tokenlyAddress = $_ENV['ETH_TOKENLY_ADDR'];
        $this->rpcClient = new Client($_ENV['ETH_RPC_SERVER']);
        $this->currentBlock = $this->rpcClient->execute('eth_blockNumber');
    }

    /**
     * Create a filter
     *
     * Returns a filter ID for two addresses
     */
    public function createNewFilter($userAddr) {
        $stepBack = self::hexdec_0x($this->currentBlock) - 10;
        $fromBlock = $stepBack < 0 ? 0 : $stepBack;
        return $this->rpcClient->execute('eth_newFilter',
                                         [['account' => [$this->tokenlyAddress, $userAddr],
                                           'fromBlock' => self::dechex_0x($fromBlock)]]);
    }

    /**
     * Check a filter
     */
    public function checkFilter($filterId) {
        return $this->rpcClient->execute('eth_getFilterChanges', [$filterId]);
    }

    /**
     * Check Balance
     *
     * Check arbitrary address balance
     */
    public function checkBalance($addr) {
        return $this->rpcClient->execute('eth_getBalance', [$addr, 'latest']);
    }

    /**
     * Dechex
     *
     * JSON RPC required '0x' to prefix dechex
     */
    public static function dechex_0x($dec) {
        return '0x' . dechex($dec);
    }

    /**
     * Hexdec
     *
     * JSON RPC will return 0x prefix so remove if it is present
     */
    public static function hexdec_0x($hex) {
        return substr($hex, 0, 2) == '0x' ? hexdec(substr($hex, 2)) : hexdec($hex);
    }

}