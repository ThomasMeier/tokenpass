<?php

namespace Tokenpass\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FetchCMSAccountInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'tkaccounts:fetch-user-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch user info from the CMS';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['username', InputArgument::REQUIRED, 'Username'],
            ['password', InputArgument::REQUIRED, 'Password'],
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
            // ['dry-run' , 'd',  InputOption::VALUE_NONE, 'Dry Run'],
        ];
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("begin");
        
        $username = $this->argument('username');
        $password = $this->argument('password');

        $loader = app('Tokenpass\Providers\CMSAuth\CMSAccountLoader');

        // $exists = $loader->usernameExists($username);
        // $this->line('$exists: '.json_encode($exists, 192));

        $results = $loader->getFullUserInfoWithLogin($username, $password);
        $this->line(json_encode($results, 192));

        $this->info("done");

    }
}
