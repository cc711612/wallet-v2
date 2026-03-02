<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InitializationWalletDetailSplitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wallet_detail_splits', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('流水號');
            $table->unsignedBigInteger('wallet_detail_id')->comment('wallet_details_id');
            $table->unsignedBigInteger('wallet_user_id')->comment('wallet_users id');
            $table->string('unit', 8)->default('TWD')->comment('貨幣單位');
            $table->float('value')->default(0)->comment('付款金額');
            $table->timestamps();
            $table->softDeletes();
            // 建立索引鍵
            $table->index(['wallet_detail_id', 'deleted_at'], 'index-wallet_detail_id-deleted_at');
            $table->foreign('wallet_user_id')->on('wallet_users')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('wallet_detail_splits');
    }
}
