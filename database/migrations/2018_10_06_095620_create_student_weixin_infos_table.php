<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStudentWeixinInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_weixin_infos', function (Blueprint $table) {
            $table->increments('id')->comment('主键id');
            $table->char('openid', 50)->unique()->index()->comment('微信openid');
            $table->string('nickname')->nullable()->comment('微信昵称');
            $table->string('avatar')->nullable()->comment('微信头像');
            $table->string('introduction')->nullable()->comment('微信简介');
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
        Schema::dropIfExists('student_weixin_infos');
    }
}
