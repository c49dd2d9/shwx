<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsBookListInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_book_list_info', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid')->index();
            $table->string('name');
            $table->string('intro');
            $table->string('nickname');
            $table->integer('focus_num');
            $table->integer('comment_num');
            $table->string('new_book_img');
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
        Schema::dropIfExists('ds_book_list_info');
    }
}
