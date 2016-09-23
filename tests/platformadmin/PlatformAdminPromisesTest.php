<?php

use Illuminate\Support\Facades\App;
use Tokenly\CurrencyLib\CurrencyUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* PlatformAdminPromisesTest
*/
class PlatformAdminPromisesTest extends TestCase {

    protected $use_database = true;

    public function testPlatformAdminPromisesRoutes() {
        $this->markTestIncomplete();
        return;

        $helper = $this->setupPlatformAdminHelper();
        $helper->beAuthorizedUser();
        
        // Skip 2 factor
        \Tokenpass\Models\UserMeta::setMeta($helper->getUser()['id'],'sign_auth','value to sign',0,0,'signed');

        $post_vars = collect(app('ProvisionalHelper')->defaultVars())->only(['source', 'destination', 'quantity', 'asset',])->all();
        $check_vars = $post_vars;
        $check_vars['quantity'] = CurrencyUtil::valueToSatoshis($check_vars['quantity']);
        $helper->testCreate($post_vars, $check_vars);
        $helper->testUpdate(array_merge($post_vars, ['source' => 'NEWSOURCE01']), array_merge($check_vars, ['source' => 'NEWSOURCE01']));
        $helper->testDelete();
    }


    ////////////////////////////////////////////////////////////////////////

    public function setUp() {
        putenv('PLATFORM_ADMIN_DEVELOPMENT_MODE_ENABLED=1');
        return parent::setUp();
    }


    protected function setupPlatformAdminHelper() {
        $platform_admin_helper = app('Tokenly\PlatformAdmin\TestHelper\PlatformAdminTestHelper');

        $platform_admin_helper
            ->setRoutePrefix('promise')
            ->setRepository(app('Tokenpass\Repositories\ProvisionalRepository'))
            ->setCreateFunction(function() {
                return app('ProvisionalHelper')->newSampleProvisional();
            });

        return $platform_admin_helper;
    }


}
