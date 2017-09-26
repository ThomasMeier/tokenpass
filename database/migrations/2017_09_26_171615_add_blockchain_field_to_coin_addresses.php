<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBlockchainFieldToCoinAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coin_addresses', function (Blueprint $table) {
            $table->enum('blockchain', ['BTC.XCP', 'ETH.ERC20'])
                ->default('BTC.XCP');
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
            $table->dropColumn('blockchain');
        });
    }
}
