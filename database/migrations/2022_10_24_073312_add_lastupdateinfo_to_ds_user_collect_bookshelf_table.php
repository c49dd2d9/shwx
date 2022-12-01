<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLastupdateinfoToDsUserCollectBookshelfTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ds_user_collect_bookshelf', function (Blueprint $table) {
            $table->bigInteger('lastupdatetime')->nullable();
            $table->string('lastupdatebookname')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ds_user_collect_bookshelf', function (Blueprint $table) {
            //
        });
    }
}
