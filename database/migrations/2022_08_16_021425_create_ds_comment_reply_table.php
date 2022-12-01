<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsCommentReplyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_comment_reply', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nickname');
            $table->string('headimg');
            $table->integer('userid');
            $table->integer('bookid');
            $table->integer('pid');
            $table->integer('chapterid');
            $table->string('content', 3000);
            $table->boolean('status');
            $table->timestamp('logtime')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ds_comment_reply');
    }
}
