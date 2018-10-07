<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWakeSignDetailInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wake_sign_detail_infos', function (Blueprint $table) {
            $table->increments('id')->comment('主键id');
            $table->char('openid', 50)->index()->comment('微信openid'); // 此表中openid可重复
            $table->integer('sign_timestamp')->comment('当日打卡时间');
            $table->integer('day_timestamp')->index()->comment('当日零点时间');
            $table->integer('sign_rank')->comment('当日打卡排名');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wake_sign_detail_infos');
    }
}
