<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFiledToDsBookUpdateInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ds_book_update_info', function (Blueprint $table) {
            $table->integer('year');
            $table->index([ 'bookid', 'year', 'month']);
            $table->dropIndex(['bookid', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ds_book_update_info', function (Blueprint $table) {
            //
        });
    }
}
