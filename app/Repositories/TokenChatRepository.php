<?php

namespace Tokenpass\Repositories;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tokenly\LaravelApiProvider\Repositories\APIRepository;
use Tokenpass\Models\TokenChat;
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

    public function findTokenChatsByAsset($asset, $active_status=true) {
        return $this->buildTokenChatsByAssetQuery($asset, $active_status)->get();
    }

    public function getTokenChatsCountByAsset($asset, $active_status=true) {
        return $this->buildTokenChatsByAssetQuery($asset, $active_status)->count();
    }

    public function buildTokenChatsByAssetQuery($asset, $active_status=true) {
        $query = $this->prototype_model
            ->select('token_chats.*')
            ->join('token_chat_access', 'token_chat_access.token_chat_id', '=', 'token_chats.id')
            ->where('token_chat_access.asset', '=', $asset);

        if ($active_status !== null) {
            $query->where('token_chats.active', '=', intval($active_status));
        }

        return $query;
    }

    public function create($attributes) {
        $token_chat = parent::create($attributes);

        $this->reindexTokenChat($token_chat);

        return $token_chat;
    }

    public function update(Model $model, $attributes) {
        $result = parent::update($model, $attributes);

        $this->reindexTokenChat($model);

        return $result;
    }

    public function delete(Model $model) {
        $result = parent::delete($model);

        // delete the access
        DB::table('token_chat_access')->where('token_chat_id', '=', $model['id'])->delete();

        return $result;
    }

    public function reindexTokenChat(TokenChat $token_chat) {
        DB::transaction(function() use ($token_chat) {
            // clear
            DB::table('token_chat_access')->where('token_chat_id', '=', $token_chat['id'])->delete();

            // insert rules if active
            $tca_rules = $token_chat['tca_rules'];
            if ($tca_rules) {
                foreach ($tca_rules as $tca_rule) {
                    if (strlen($tca_rule['asset'])) {
                        DB::table('token_chat_access')->insert([
                            'token_chat_id' => $token_chat['id'],
                            'asset'         => $tca_rule['asset'],
                            'amount'        => $tca_rule['amount'],
                        ]);
                    }
                }
            }
        });
    }
}
