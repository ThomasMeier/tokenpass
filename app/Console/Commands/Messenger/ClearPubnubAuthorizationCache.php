<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;

class ClearPubnubAuthorizationCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:clear-auth-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all pubnub authorizaton caches';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->comment('begin');
        app('Tokenpass\Providers\TCAMessenger\TCAMessengerAuth')->clearAllCaches();
        $this->comment('done');
    }
}
