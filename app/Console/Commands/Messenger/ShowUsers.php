<?php

namespace Tokenpass\Console\Commands\Messenger;

use Illuminate\Console\Command;
use Tokenpass\Repositories\UserRepository;

class ShowUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messenger:show-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List users';

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
        $user_repository = app(UserRepository::class);
        $users = $user_repository->findAll();

        $bool = function($val) { return $val ? '<info>true</info>' : '<comment>false</comment>'; };
        $headers = ['id','uuid','username','channel','authKey'];
        $rows = [];
        foreach($users as $user) {
            $rows[] = [
                $user['id'],
                $user['uuid'],
                $user['username'],
                $user->getChannelName(),
                $user->getChannelAuthKey(),
            ];
        }

        $this->table($headers, $rows);
    }
}
