<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeleteIndexToDsBookCommentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ds_book_comment', function (Blueprint $table) {
            $table->dropIndex([ 'bookid', 'is_long']);
            $table->dropIndex([ 'bookid' ]);
            $table->dropIndex([ 'bookid', 'is_long', 'status']);
            $table->dropIndex([ 'bookid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ds_book_comment', function (Blueprint $table) {
            //
        });
    }
}
