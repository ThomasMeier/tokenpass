<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Events\UserRegistered;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Repositories\TokenChatRepository;
use Tokenpass\Repositories\UserRepository;

class ResyncUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:resync-user 
                            {--f|full : Do a full re-authorization}
                            {userId : UUID or username of the User}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-syncs permissions for a user';

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
        $user_id                 = $this->argument('userId');
        $do_full_reauthorization = $this->option('full');

        $user_repository = app(UserRepository::class);
        $user = $user_repository->findByUuid($user_id);
        if (!$user) {
            $user = $user_repository->findByUsername($user_id);
        }
        if (!$user) {
            $this->error("User not found for id $user_id");
            return;
        }

        if ($do_full_reauthorization) {
            $this->line('Temporarily removing all access for user '.$user['username'].' ('.$user['name'].', '.$user['uuid'].')');
            app(TCAMessenger::class)->clearUserGrantCaches($user);
        }

        $this->line('Resyncing access for user '.$user['username'].' ('.$user['name'].', '.$user['uuid'].')');

        event(new UserRegistered($user));


        $this->comment('done');
    }
}
