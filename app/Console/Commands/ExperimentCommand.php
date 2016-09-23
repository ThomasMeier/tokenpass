<?php

namespace Tokenpass\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Tokenly\PlatformAdmin\Meta\PlatformAdminMeta;

class ExperimentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'tokenpass:exp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Experiment';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            // ['username', InputArgument::REQUIRED, 'Username'],
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

        PlatformAdminMeta::setMulti([
            'foo1' => 'barz1',
            'foo4' => ['a' => 'barz4', 'b'=>'barz4'],
        ]);
        PlatformAdminMeta::set('foo3', 'bar3');
        $res = PlatformAdminMeta::getMulti(['foo1','foo2','foo3',]);
        echo "\$res: ".json_encode($res, 192)."\n";

        $this->info("done");

    }
}
