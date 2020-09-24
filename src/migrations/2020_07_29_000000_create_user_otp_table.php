<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserOtpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_otp', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email', 256);
            $table->string('phone', 256);
            $table->string('code', 8);
            $table->boolean('is_verified')->default(0);
            $table->datetime('created_at');
            $table->datetime('updated_at');

            $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('otp_enabled')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp_enabled');

        });
        Schema::dropIfExists('user_otp');
    }
}
