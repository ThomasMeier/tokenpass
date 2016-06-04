<?php

namespace TKAccounts\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \Tokenly\LaravelApiProvider\Commands\MakeAPIModelCommand::class,
        \Tokenly\LaravelApiProvider\Commands\MakeAPIRespositoryCommand::class,

        \TKAccounts\Console\Commands\PopulateCMSUsernamesCacheCommand::class,
        \TKAccounts\Console\Commands\FetchCMSAccountInfoCommand::class,
        \TKAccounts\Console\Commands\ScanCoinAddresses::class,
        \TKAccounts\Console\Commands\ExpireProvisionalTransactions::class,
        \TKAccounts\Console\Commands\ListUserAddresses::class,

        // Migration commands
        \TKAccounts\Console\Commands\Migrations\SyncUnmanagedAddressesWithXChain::class,

        \Tokenly\ConsulHealthDaemon\Console\ConsulHealthMonitorCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('scanCoinAddresses')->everyThirtyMinutes();
        $schedule->command('tokenpass:expireProvisionalTransactions')->everyFiveMinutes();
    }
}
