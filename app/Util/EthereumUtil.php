<?php

namespace Tokenpass\Util;

use JsonRPC\Client;

/**
 * Ethereum Util
 *
 */
class EthereumUtil {

    const BLOCKS_PER_HOUR = 125;

    protected $rpcClient;

    protected $tokenlyAddress;

    protected $currentBlock;

    public function __construct() {
        $this->tokenlyAddress = $_ENV['ETH_TOKENLY_ADDR'];
        $this->rpcClient = new Client($_ENV['ETH_RPC_SERVER']);
        $this->currentBlock = $this->rpcClient->execute('eth_blockNumber');
    }

    /**
     * Check all previous blocks' TXs for userAddr and amt (in wei)
     *
     * Approximately 3000 blocks per day at time of writing just statically
     *
     * @return bool true if found in recent blocks
     */
    public function searchBlocks($userAddr, $amt) {
        $stepBack = self::hexdec_0x($this->currentBlock) - self::BLOCKS_PER_HOUR;
        $fromBlock = $stepBack < 0 ? 0 : $stepBack;
        $txFound = false;
        for ($i = self::hexdec_0x($this->currentBlock); $i >= $fromBlock; $i--) {
            $block = $this->rpcClient->execute('eth_getBlockByNumber', [self::dechex_0x($i), true]);
            if ($this->checkBlockTx($block, $userAddr, $amt)) {
                $txFound = true;
                break;
            }
        }
        return $txFound;
    }

    /**
     * Checks a block's transaction array for userAddr and amt (in wei)
     *
     * @return bool true if tx found in block
     */
    private function checkBlockTx($block, $userAddr, $amt) {
        $txFound = false;
        foreach ($block['transactions'] as $tx) {
            if ($tx['to'] == $this->tokenlyAddress
                && $tx['from'] == $userAddr
                && self::hexdec_0x($tx['value']) == self::hexdec_0x($amt)) {
                $txFound = true;
                break;
            }
        }
        return $txFound;
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