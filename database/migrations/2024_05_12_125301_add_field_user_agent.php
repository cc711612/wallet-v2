<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldUserAgent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('agent', 256)->nullable()->comment('agent')->after('token');
            $table->string('ip', 64)->nullable()->comment('ip')->after('token');
        });

        Schema::table('wallet_users', function (Blueprint $table) {
            //
            $table->string('agent', 256)->nullable()->comment('agent')->after('token');
            $table->string('ip', 64)->nullable()->comment('ip')->after('token');
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
            //
            $table->dropColumn('agent');
            $table->dropColumn('ip');
        });

        Schema::table('wallet_users', function (Blueprint $table) {
            //
            $table->dropColumn('agent');
            $table->dropColumn('ip');
        });
    }
}
