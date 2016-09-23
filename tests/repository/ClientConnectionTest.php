<?php

use \PHPUnit_Framework_Assert as PHPUnit;

/*
* ClientConnectionRepositoryTest
*/
class ClientConnectionRepositoryTest extends TestCase {

    protected $use_database = true;

    public function testClientConnectionRepository()
    {
        $helper = $this->createRepositoryTestHelper();

        $helper->cleanup()->testLoad();
        $helper->cleanup()->testDelete();
        $helper->cleanup()->testFindAll();
    }

    protected function createRepositoryTestHelper() {
        $create_model_fn = function() {
            return app('ClientConnectionHelper')->newSampleConnection();
        };

        $helper = new RepositoryTestHelper($create_model_fn, $this->app->make('Tokenpass\Repositories\ClientConnectionRepository'));
        return $helper;
    }

}
