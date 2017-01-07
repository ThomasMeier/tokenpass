<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreditGroupIdToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('app_credit_transactions', function (Blueprint $table) {
            $table->integer('app_credit_group_id')->unsigned();
            $table->index('app_credit_group_id');
            $table->foreign('app_credit_group_id')
                ->references('id')->on('app_credit_groups')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_credit_transactions', function (Blueprint $table) {
            $table->dropColumn('app_credit_group_id');
        });
    }
}
