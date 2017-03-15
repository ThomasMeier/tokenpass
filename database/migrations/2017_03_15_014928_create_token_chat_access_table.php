<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTokenChatAccessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('token_chat_access', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('token_chat_id')->unsigned();
            $table->foreign('token_chat_id')
                  ->references('id')->on('token_chats')
                  ->onDelete('cascade');

            $table->string('asset')->index();
            $table->bigInteger('amount')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('token_chat_access');
    }
}
