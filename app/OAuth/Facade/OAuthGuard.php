<?php

namespace Tokenpass\OAuth\Facade;

use Illuminate\Support\Facades\Facade;

class OAuthGuard extends Facade {


    protected static function getFacadeAccessor() { return 'oauthguard'; }


}
