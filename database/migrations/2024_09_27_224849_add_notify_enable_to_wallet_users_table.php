<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotifyEnableToWalletUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallet_users', function (Blueprint $table) {
            //
            $table->boolean('notify_enable')->default(0)->comment('Notify Enable')->after('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallet_users', function (Blueprint $table) {
            //
            $table->dropColumn('notify_enable');
        });
    }
}
