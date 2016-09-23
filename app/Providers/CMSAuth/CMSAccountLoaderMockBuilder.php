<?php

namespace Tokenpass\Providers\CMSAuth;

use Exception;
use Illuminate\Support\Facades\Log;

class CMSAccountLoaderMockBuilder {


   public static function installMockCMSAccountLoader() {
        $test_case = new \Tokenpass\Providers\CMSAuth\Mock\MockTestCase();

        $loader_mock = $test_case->getMockBuilder('Tokenpass\Providers\CMSAuth\CMSAccountLoader')
            ->disableOriginalConstructor()
            ->getMock();

        // install the pusher client into the DI container
        app()->bind('Tokenpass\Providers\CMSAuth\CMSAccountLoader', function($app) use ($loader_mock) {
            return $loader_mock;
        });

   }
}