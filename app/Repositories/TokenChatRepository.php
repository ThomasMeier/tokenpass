<?php

namespace Tokenpass\Repositories;

use Exception;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Tokenpass\Models\User;

/*
* TokenChatRepository
*/
class TokenChatRepository extends APIRepository
{

    protected $model_type = 'Tokenpass\Models\TokenChat';

    public function findAllByUser(User $user) {
        return $this->prototype_model->where('user_id', $user['id'])->get();
    }

}
