<?php

use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Tokenpass\Models\Address;
use Tokenpass\Models\AppCreditAccount;
use Tokenpass\Models\AppCreditTransaction;
use Tokenpass\Models\AppCredits;
use Tokenpass\Models\User;
use Tokenpass\Providers\PseudoAddressManager\PseudoAddressManager;

/*
* AppCreditsHelper
*/
class AppCreditsHelper
{
    
    public function defaultAppCreditGroup($userId, $app_whitelist = array(), $event_slug=null)
    {
        $vals = $this->getDefaultAppCreditVals();
        $vals['user_id'] = $userId;
        $vals['app_whitelist'] = join("\n", $app_whitelist);
        $get = AppCredits::where('uuid', $vals['uuid'])->first();
        if($get){
            return $get;
        }

        if ($event_slug !== null) {
            $vals['event_slug'] = $event_slug;
            $vals['publish_events'] = true;
        }

        return AppCredits::create($vals);
    }
    
    public function newAppCreditGroup(User $user, $override_vars=[])
    {
        if (!isset($override_vars['uuid'])) {
            $override_vars['uuid'] = Uuid::uuid4();
        }
        $override_vars['user_id'] = $user['id'];

        return AppCredits::create(array_merge($this->getDefaultAppCreditVals(), $override_vars));
    }
    
    public function newAppCreditAccountForUser(User $user, AppCredits $credit_group) {
        $account = new AppCreditAccount();
        $account->app_credit_group_id = $credit_group->id;
        $account->name = $user['uuid'];        
        $account->uuid = Uuid::uuid4()->toString();
        $account->save();

        return $account;
    }
    
    public function creditAccount($credit_amount, AppCredits $credit_group, AppCreditAccount $account, AppCreditAccount $debit_source=null, $ref=null) {
        if ($debit_source === null) {
            $debit_source = $credit_group->getDefaultAccount();
        }

        $credit_tx = AppCreditTransaction::newTX($credit_group->id, $account->id, $credit_amount, $ref);
        $debit_tx = AppCreditTransaction::newTX($credit_group->id, $debit_source->id, 0-$credit_amount, $ref);

        return [$credit_tx, $debit_tx];
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
