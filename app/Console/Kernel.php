<?php

namespace Tokenpass\Console;

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

        \Tokenpass\Console\Commands\PopulateCMSUsernamesCacheCommand::class,
        \Tokenpass\Console\Commands\FetchCMSAccountInfoCommand::class,
        \Tokenpass\Console\Commands\ScanCoinAddresses::class,
        \Tokenpass\Console\Commands\ExpireProvisionalTransactions::class,
        \Tokenpass\Console\Commands\ListUserAddresses::class,
        \Tokenpass\Console\Commands\ListUsers::class,
        \Tokenpass\Console\Commands\GetUser::class,

        // Migration Commands
        \Tokenpass\Console\Commands\Migrations\SyncUnmanagedAddressesWithXChain::class,

        // Monitor Health
        \Tokenly\ConsulHealthDaemon\Console\ConsulHealthMonitorCommand::class,

        // Platform Admin
        \Tokenly\PlatformAdmin\Console\CreatePlatformAdmin::class,
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
