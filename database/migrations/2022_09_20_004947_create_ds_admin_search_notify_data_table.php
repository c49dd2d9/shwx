<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDsAdminSearchNotifyDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ds_admin_search_notify_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('content', 2000);
            $table->string('all_uuid', 100)->index();
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
        Schema::dropIfExists('ds_admin_search_notify_data');
    }
}
