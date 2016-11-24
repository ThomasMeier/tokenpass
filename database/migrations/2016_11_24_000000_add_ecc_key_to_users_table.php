<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tokenpass\Util\ECCUtil;

class AddEccKeyToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ecc_key')->nullable();
        });

        // generate a key for every user
        DB::transaction(function() {
            $all_users = DB::table('users')->where('id', '>', 0)->lockForUpdate()->get();
            $count = count($all_users);
            foreach($all_users as $offset => $user) {
                // generate a key
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['ecc_key' => ECCUtil::generateEncodedPrivateKey()]);
                if ($offset % 50 == 0 OR $offset == $count - 1) {
                    Log::debug("Completed ".($offset+1)." of $count users");
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ecc_key');
        });
    }
}
