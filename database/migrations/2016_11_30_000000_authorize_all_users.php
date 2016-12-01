<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tokenpass\Providers\TCAMessenger\TCAMessenger;
use Tokenpass\Util\ECCUtil;

class AuthorizeAllUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // authorize the control channel of every current user
        DB::transaction(function() {
            app(TCAMessenger::class)->authorizeAllUsers();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
