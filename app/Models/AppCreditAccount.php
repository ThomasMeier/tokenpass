<?php
namespace Tokenpass\Models;

use DB, Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;

class AppCreditAccount extends Model
{
    protected $table = 'app_credit_accounts';
    public $timestamps = true;
    
    
    
}
