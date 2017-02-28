<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventDataToAppCreditGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('app_credit_groups', function (Blueprint $table) {
            $table->boolean('publish_events')->default(0);
            $table->string('event_slug')->unique()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_credit_groups', function (Blueprint $table) {
            $table->dropColumn('event_slug');
            $table->dropColumn('publish_events');
        });
    }
}
