<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class AddUuidToAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coin_addresses', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable();
        });

        // seed all existing addresses with a uuid
        foreach (DB::table('coin_addresses')->get() as $address) {
            $new_uuid = Uuid::uuid4()->toString();
            DB::table('coin_addresses')
                ->where('id', $address->id)
                ->update(['uuid' => $new_uuid]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coin_addresses', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
