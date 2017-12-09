<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Tokenpass\Events\AddressBalanceChanged;
use Tokenpass\Models\Address;
use Tokenpass\Util\EthereumUtil;

class ScanCoinAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scanETHAddresses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Picks up ETH addresses and updates account balances';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $eth = new EthereumUtil();
        $address_list = Address::where('verified', '=', 1)->where('blockchain', '=', 'ETH.ERC20')->get();
        if (!$address_list OR count($address_list) == 0){
            return false;
        }
        $stamp = date('Y-m-d H:i:s');
        foreach($address_list as $row){
            $balance = $eth->checkBalance($row->address);
            if($balance AND count($balance) > 0)
            {
                $balance_dec = EthereumUtil::hexdec_0x($balance);
                $update = Address::updateAddressBalances($row->id, ['ether' => $balance_dec]);
                if(!$update)
                {
                    $this->error('Failed updating '.$row->address.' ['.$row->id.']');
                }
                else
                {
                    $this->info('Updated '.$row->address.' ['.$row->id.']');

                    // fire an address balanced changed event
                    Event::fire(new AddressBalanceChanged($row));
                }
            }
        }
    }
}
