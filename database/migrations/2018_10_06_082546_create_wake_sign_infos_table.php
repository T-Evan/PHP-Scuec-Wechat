<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWakeSignInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wake_sign_infos', function (Blueprint $table) {
            $table->increments('id')->comment('主键id');
            $table->char('openid', 50)->unique()->index()->comment('微信openid');
            $table->integer('sign_day')->unsigned()->index()->comment('总打卡天数');
            $table->float('sign_score')->unsigned()->default(0)->comment('总打卡积分');
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
        Schema::dropIfExists('wake_sign_infos');
    }
}
