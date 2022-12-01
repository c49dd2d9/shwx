<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsCommentNotifyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_comment_notify', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->integer('status');
            $table->integer('bookid');
            $table->string('bookname');
            $table->string('message');
            $table->index(['userid', 'status']);
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
        Schema::dropIfExists('ds_comment_notify');
    }
}
