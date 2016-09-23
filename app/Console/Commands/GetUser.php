<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Tokenpass\Models\User;

class GetUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokenpass:getUser {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets DB details on a user';

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
        $username = $this->argument('user');
        $get = User::where('username', $username)->orWhere('confirmed_email', $username)->orWhere('uuid', $username)->first();
        if(!$get){
            $this->error('User not found');
            return false;
        }
        var_dump($get->toArray());
        return true;
    }
}
