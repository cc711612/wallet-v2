<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldWalletDetailsUnit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallet_details', function (Blueprint $table) {
            //
            $table->string('unit', 8)->default('TWD')->comment('貨幣單位')->after('value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallet_details', function (Blueprint $table) {
            //
            $table->dropColumn('unit');
        });
    }
}
