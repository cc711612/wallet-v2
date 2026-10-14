<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 匯率欄位加大：decimal(10,4) 最大 999,999.9999，USD→IRR 已超過，改為 decimal(20,8)。
     */
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('rate', 20, 8)->comment('匯率值')->change();
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('rate', 10, 4)->comment('匯率值')->change();
        });
    }
};
