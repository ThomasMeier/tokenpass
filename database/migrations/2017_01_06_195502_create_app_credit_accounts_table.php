<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppCreditAccountsTable extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::create('app_credit_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('app_credit_group_id')->unsigned();
            $table->index('app_credit_group_id');
            $table->foreign('app_credit_group_id')
                ->references('id')->on('app_credit_groups')
                ->onDelete('cascade');
            $table->string('uuid', 36)->default('')->nullable(true)->unique();
            $table->index('uuid');
            $table->string('name');
            $table->index('name');
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
        Schema::dropIfExists('app_credit_accounts');
    }
}
