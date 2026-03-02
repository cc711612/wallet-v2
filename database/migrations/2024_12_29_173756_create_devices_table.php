<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('使用者ID');
            $table->unsignedBigInteger('wallet_user_id')->nullable()->comment('使用者ID');
            $table->string('platform')->nullable()->comment('平台');
            $table->string('device_name')->nullable()->comment('裝置名稱');
            $table->string('device_type')->nullable()->comment('裝置類型');
            $table->string('fcm_token')->nullable()->comment('FCM Token');
            $table->timestamp('expired_at')->nullable()->comment('過期時間');
            $table->timestamp('created_at')->useCurrent()->comment('建立時間');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新時間');
            $table->softDeletes();
            $table->index(['wallet_user_id', 'fcm_token', 'expired_at', 'deleted_at'], 'index_wallet_user_id_expired_at_deleted_at');
            $table->index(['user_id', 'fcm_token', 'expired_at', 'deleted_at'], 'index_user_id_expired_at_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('devices');
    }
}
