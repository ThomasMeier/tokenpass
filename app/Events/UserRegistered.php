<?php

namespace Tokenpass\Events;

use Illuminate\Queue\SerializesModels;
use Tokenpass\Models\User;

class UserRegistered
{
    use SerializesModels;

    var $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

}
