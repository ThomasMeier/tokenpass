<?php

namespace Tokenpass\Events;

use Illuminate\Queue\SerializesModels;
use Tokenpass\Models\AppCreditAccount;

class CreditsUpdated
{
    use SerializesModels;

    var $account;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(AppCreditAccount $account)
    {
        $this->account = $account;
    }

}
