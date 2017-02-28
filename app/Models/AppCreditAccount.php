<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Tokenly\LaravelEventLog\Facade\EventLog;
use Tokenpass\Models\AppCreditTransaction;
use Tokenpass\Models\AppCredits;
use Tokenpass\Repositories\UserRepository;

class AppCreditAccount extends Model
{
    protected $table = 'app_credit_accounts';
    public $timestamps = true;
    
    protected $fillable = ['name', 'uuid'];
    
    
    public function calculateBalance() {
        return AppCreditTransaction::where('app_credit_account_id', $this->id)->sum('amount');
    }

    public function appCreditGroup() {
        return $this->belongsTo(AppCredits::class, 'app_credit_group_id');
    }

    public function getUser() {
        $user_uuid = $this->name;
        if (!$this->looksLikeAUuid($user_uuid)) {
            return null;
        }

        $user_repository = app(UserRepository::class);
        return $user_repository->findByUuid($user_uuid);
    }

    // ------------------------------------------------------------------------
    
    protected function looksLikeAUuid($user_uuid) {
        return ((strstr($user_uuid, '-') !== false) AND Uuid::isValid($user_uuid));
    }

}