<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePubnubTokenpassAccessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pubnub_tokenpass_access', function (Blueprint $table) {
            $table->increments('id');

            $table->string('channel');
            $table->integer('ttl');
            $table->boolean('read');
            $table->boolean('write');

            $table->timestamp('updated_at');

            $table->unique(['channel', 'ttl', 'read', 'write']);  
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pubnub_tokenpass_access');
    }
}
