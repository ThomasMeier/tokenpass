<?php

use Tokenpass\Providers\CMSAuth\CMSAccountLoaderMockBuilder;

class TestCase extends Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';


    protected $use_database = false;

    public function setUp()
    {
        parent::setUp();

        if ($this->use_database) { $this->setUpDb(); }

        CMSAccountLoaderMockBuilder::installMockCMSAccountLoader();
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }


    public function setUpDb()
    {
        $this->app['Illuminate\Contracts\Console\Kernel']->call('migrate');
    }

    public function teardownDb()
    {
    }

}


