<?php

namespace Tokenpass\Console\Commands;

use Illuminate\Console\Command;
use Tokenpass\Models\User;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokenpass:listUsers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all users in system';

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
        $users = User::select('*')->orderBy('id', 'asc')->get();
        if(!$users){
            $this->error('Error getting users');
            return false;
        }
        foreach($users as $user){
            $text = '[#'.$user->id.'] '.$user->username.' - '.$user->email.' ['.$user->uuid.']';
            $this->info($text);
        }
        $this->info('..done ('.count($users).')');
    }
}
