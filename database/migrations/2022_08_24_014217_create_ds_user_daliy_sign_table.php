<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsUserDaliySignTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_user_daliy_sign', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->integer('month');
            $table->string('signlog');
            $table->index(['userid', 'month']);
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
        Schema::dropIfExists('ds_user_daliy_sign');
    }
}
