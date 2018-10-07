<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStudentInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_infos', function (Blueprint $table) {
            $table->increments('id')->comment('主键id');
            $table->char('openid', 50)->unique()->index()->comment('微信openid');
            $table->string('account', 300)->nullable()->index()->comment('教务系统账号');
            $table->string('ssfw_password', 300)->nullable()->comment('教务系统及办事大厅密码');
            $table->string('lib_password', 300)->nullable()->comment('图书馆密码');
            $table->string('lab_password', 300)->nullable()->comment('大物实验查询密码');
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
        Schema::dropIfExists('student_infos');
    }
}
