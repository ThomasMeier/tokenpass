<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppCreditTransactionsTable extends Migration
{
    /**
     * Run the migrations
     * @return void
     */
    public function up()
    {
        Schema::create('app_credit_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('app_credit_account_id')->unsigned();
            $table->index('app_credit_account_id');
            $table->foreign('app_credit_account_id')
                ->references('id')->on('app_credit_accounts')
                ->onDelete('cascade');
            $table->string('uuid', 36)->default('')->nullable(true)->unique();
            $table->index('uuid');
            $table->string('ref')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_credit_transactions');
    }
}
