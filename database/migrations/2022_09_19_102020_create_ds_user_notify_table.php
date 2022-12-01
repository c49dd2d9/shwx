<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsUserNotifyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_user_notify_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->string('title');
            $table->string('content', 2000);
            $table->tinyInteger('type');
            $table->boolean('read_status');
            $table->index([ 'userid', 'type', 'read_status' ]);
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
        Schema::dropIfExists('ds_user_notify_data');
    }
}
