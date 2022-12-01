<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsBookRecommendTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_book_recommend', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('bookid');
            $table->string('bookinfo', 1000);
            $table->integer('recommendid');
            $table->integer('classid');
            $table->index('recommendid');
            $table->index([ 'recommendid', 'classid' ]);
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
        Schema::dropIfExists('ds_book_recommend');
    }
}
