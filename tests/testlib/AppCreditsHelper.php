<?php

use Illuminate\Support\Facades\Log;
use Tokenpass\Models\Address;
use Tokenpass\Models\User;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;
use Tokenpass\Models\AppCredits;
use Tokenpass\Models\AppCreditAccount;
use Tokenpass\Models\AppCreditTransaction;

/*
* AppCreditsHelper
*/
class AppCreditsHelper
{
    
    public function defaultAppCreditGroup($userId, $app_whitelist = array())
    {
        $vals = $this->getDefaultAppCreditVals();
        $vals['user_id'] = $userId;
        $vals['app_whitelist'] = join("\n", $app_whitelist);
        $get = AppCredits::where('uuid', $vals['uuid'])->first();
        if($get){
            return $get;
        }
        return AppCredits::create($vals);
    }
    
    
    public function getDefaultAppCreditVals()
    {
        return array(
            'uuid' => '3a4572d5-c7b4-4b50-9594-5c31b4bafc45',
            'name' => 'Store Credits',
            'active' => true,
            'app_whitelist' => null,
            'created_at' => '2017-01-01 00:00:00',
            'updated_at' => '2017-01-01 00:00:00',
        );
        
    }
    
}
