<?php

namespace Tokenpass\OAuth\Facade;

use Illuminate\Support\Facades\Facade;

class OAuthClientGuard extends Facade {


    protected static function getFacadeAccessor() { return 'oauthclientguard'; }


}
