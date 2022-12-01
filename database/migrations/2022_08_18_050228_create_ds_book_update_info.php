<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsBookUpdateInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_book_update_info', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('bookid');
            $table->tinyInteger('month');
            $table->tinyInteger('daily');
            $table->integer('wordcnt');
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
        Schema::dropIfExists('ds_book_update_info');
    }
}
