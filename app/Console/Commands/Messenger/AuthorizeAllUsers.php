<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Repositories\TokenChatRepository;

class AuthorizeAllUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:authorize-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds basic control channel authorization for all users';

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

        app('Tokenpass\Providers\TCAMessenger\TCAMessenger')->authorizeAllUsers();

        $this->comment('done');
    }
}
