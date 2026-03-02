<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldWalletDetailsIsPersonal extends Migration
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
            $table->boolean('is_personal')->default(0)->comment('個人明細')->after('value');
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
            $table->dropColumn('is_personal');
        });
    }
}
