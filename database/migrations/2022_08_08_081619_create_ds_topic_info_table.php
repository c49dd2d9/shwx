<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsTopicInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_topic_info', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nickname');
            $table->string('title');
            $table->string('content', 3000);
            $table->integer('userid');
            $table->integer('boardid');
            $table->boolean('is_delete');
            $table->integer('replies');
            $table->bigInteger('lastpost');
            $table->index(['boardid', 'is_delete', 'lastpost']);
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
        Schema::dropIfExists('ds_topic_info');
    }
}
