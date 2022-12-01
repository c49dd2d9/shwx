<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatedDsBookSearchIndexTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_book_search_index', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('bookname');
            $table->string('booktag');
            $table->integer('yuanchuangxing');
            $table->integer('isvip');
            $table->integer('xingxiang');
            $table->integer('shijiao');
            $table->integer('times');
            $table->integer('book_tpe');
            $table->string('role');
            $table->integer('gold');
            $table->integer('collectcnt');
            $table->integer('viewcnt');
            $table->integer('userid');
            $table->string('writername');
            $table->bigInteger('lastupdate');
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
        Schema::dropIfExists('ds_book_search_index');
    }
}
