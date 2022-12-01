<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatedDsBanUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_ban_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->integer('moduleid');
            $table->bigInteger('expired_time');
            $table->index([ 'userid', 'moduleid' ]);
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
        Schema::dropIfExists('ds_ban_user');
    }
}
