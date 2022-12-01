<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsApplySignTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_apply_sign', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('bookid');
            $table->integer('userid');
            $table->boolean('status');
            $table->string('bookname');
            $table->string('nickname');
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
        Schema::dropIfExists('ds_apply_sign');
    }
}
