<?php namespace Arx\Auth\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddEveOnlineFieldsToUsersTable extends Migration
{

    public function up()
    {
        Schema::table('users', function($table)
        {
            $table->string('display_name')->nullable();
            $table->integer('character_id')->nullable();
            $table->string('character_name')->nullable();
            $table->integer('corporation_id')->nullable();
            $table->string('corporation_ticker')->nullable();
            $table->string('corporation_name')->nullable();
            $table->integer('alliance_id')->nullable();
            $table->string('alliance_name')->nullable();
        });
    }

    public function down()
    {
        $table->dropDown([
            'display_name',
            'character_id',
            'character_name',
            'corporation_id',
            'corporation_ticker',
            'corporation_name',
            'alliance_id',
            'alliance_name',
        ]);
    }


}
