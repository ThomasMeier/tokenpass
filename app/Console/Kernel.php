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

        Commands\PopulateCMSUsernamesCacheCommand::class,
        Commands\FetchCMSAccountInfoCommand::class,
        Commands\ScanCoinAddresses::class,
        Commands\ExpireProvisionalTransactions::class,
        Commands\ListUserAddresses::class,
        Commands\ListUsers::class,
        Commands\GetUser::class,

        // messenger
        Commands\Messenger\ClearPubnubAuthorizationCache::class,
        Commands\Messenger\ResyncChat::class,
        Commands\Messenger\ShowChats::class,
        Commands\Messenger\ShowUsers::class,
        Commands\Messenger\AuthorizeUser::class,
        Commands\Messenger\AuthorizeAllUsers::class,

        // messenger (dev)
        Commands\MessengerDev\DeleteChatByChannel::class,

        // Migration Commands
        Commands\Migrations\SyncUnmanagedAddressesWithXChain::class,

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
