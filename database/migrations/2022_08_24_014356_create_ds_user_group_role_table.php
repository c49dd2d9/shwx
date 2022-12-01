<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsUserGroupRoleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_user_group_role', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('level')->index();
            $table->integer('experience');
            $table->tinyInteger('bookshelf_count');
            $table->tinyInteger('bookshelf_series_count');
            $table->tinyInteger('book_list_count');
            $table->tinyInteger('focus_count');
            $table->tinyInteger('signin_recommended_ticket');
            $table->tinyInteger('signin_gold');
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
        Schema::dropIfExists('ds_user_group_role');
    }
}
