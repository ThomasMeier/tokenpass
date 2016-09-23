<?php

use \PHPUnit_Framework_Assert as PHPUnit;

/*
* ProvisionalRepositoryTest
*/
class ProvisionalRepositoryTest extends TestCase {

    protected $use_database = true;

    public function testProvisionalRepository()
    {
        $helper = $this->createRepositoryTestHelper();

        $helper->cleanup()->testLoad();
        $helper->cleanup()->testUpdate(['expiration' => date("Y-m-d H:i:s", time() + 3600)]);
        $helper->cleanup()->testDelete();
        $helper->cleanup()->testFindAll();
    }

    protected function createRepositoryTestHelper() {
        $create_model_fn = function() {
            return app('ProvisionalHelper')->newSampleProvisional();
        };

        $helper = new RepositoryTestHelper($create_model_fn, $this->app->make('Tokenpass\Repositories\ProvisionalRepository'));
        $helper->use_uuid = false;
        return $helper;
    }

}
