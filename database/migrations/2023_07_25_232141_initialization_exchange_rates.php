<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InitializationExchangeRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency')->default('TWD')->comment('原始貨幣代碼');
            $table->string('to_currency')->nullable()->comment('目標貨幣代碼');
            $table->decimal('rate', 10, 4)->comment('匯率值'); // 使用 decimal 來儲存匯率值，總共 10 位數，其中 4 位是小數部分
            $table->date('date')->comment('時間');
            $table->timestamps();
            // 建立索引鍵
            $table->index(['date', 'to_currency'], 'date-to_currency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exchange_rates');
    }
}
