<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsChapterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_chapter_job', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('bookid');
            $table->integer('chapterid');
            $table->integer('wordcnt');
            $table->integer('publishtime');
            $table->boolean('state')->index();
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
        Schema::dropIfExists('ds_chapter_job');
    }
}
