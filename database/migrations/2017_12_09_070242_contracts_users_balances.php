<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ContractsUsersBalances extends Migration
{
    /**
     * Many to many relationship between contract_abis and users, with balances
     *
     * When a user registers a new address with a contract, the default balance
     * is zero.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contracts_users_balances', function (Blueprint $table) {
            $table->string('eth_address');
            $table->integer('contract_id');
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamp('updated_at');
            $table->timestamp('created_at');
            $table->index('eth_address');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contracts_users_balances');
    }
}
