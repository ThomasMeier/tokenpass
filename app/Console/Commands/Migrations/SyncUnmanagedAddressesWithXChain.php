<?php

namespace Tokenpass\Console\Commands\Migrations;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Swapbot\Swap\Logger\BotEventLogger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SyncUnmanagedAddressesWithXChain extends Command {


    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'swapbotmigrate:sync-unmanaged-addresses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates unmanaged addresses to managed addresses';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            // ['event-name', InputArgument::REQUIRED, 'An event name to archive'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            // ['limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of items to archive.', null],
        ];
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            // get all addresses
            $all_addresses = app('Tokenpass\Repositories\AddressRepository')->findAll();
            $count = $all_addresses->count();
            foreach ($all_addresses as $offset => $address) {
                $this->info('Syncing address '.$address['address'].' ('.($offset+1).' of '.$count.')');
                $address->syncWithXChain();
            }
        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());
            throw $e;
        }

        $this->comment('Done. synced '.$count.' addresses.');
    }

}
