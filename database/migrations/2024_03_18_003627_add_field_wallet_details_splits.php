<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldWalletDetailsSplits extends Migration
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
            $table->decimal('rates')->nullable()->comment('unit rates')->after('value');
            $table->json('splits')->comment('details splits')->after('value');
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
            $table->dropColumn('rates');
            $table->dropColumn('splits');
        });
    }
}
