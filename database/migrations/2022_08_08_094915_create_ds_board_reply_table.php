<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsBoardReplyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        for ($i=1; $i < 10; $i++) { 
            Schema::create('ds_board_reply_'.$i, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('topicid')->index();
                $table->integer('boardid');
                $table->integer('userid');
                $table->string('nickname');
                $table->string('content');
                $table->string('avatar');
                $table->integer('level');
                $table->integer('floor');
                $table->boolean('is_delete');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ds_board_reply');
    }
}
