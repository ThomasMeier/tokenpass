<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVerifyAddressColumnToCoinAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coin_addresses', function (Blueprint $table) {
            $table->string('verify_address');
            $table->string('verify_address_uuid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coin_addresses', function (Blueprint $table) {
            $table->dropColumn(['verify_address', 'verify_address_uuid']);
        });
    }
}
