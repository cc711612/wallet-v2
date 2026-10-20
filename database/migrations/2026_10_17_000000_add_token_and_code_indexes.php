<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 每個帶 member_token 的請求都以 token 查 users／wallet_users，第三方登入以
     * (social_type, social_type_value) 查 socials，帳本以 code 查 wallets，原本都沒有 index。
     * 已存在相同欄位組合的 index 時略過（正式環境可能已手動加過）。
     *
     * wallets.code 只加一般 index；改 UNIQUE 需先確認正式資料沒有重複 code（人工處理）。
     *
     * @var array<int, array{0:string, 1:array<int,string>, 2:string}>
     */
    private array $indexes = [
        ['users', ['token'], 'users_token_index'],
        ['wallet_users', ['token'], 'wallet_users_token_index'],
        ['socials', ['social_type', 'social_type_value'], 'socials_social_type_social_type_value_index'],
        ['wallets', ['code'], 'wallets_code_index'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (Schema::hasIndex($table, $columns)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, , $name]) {
            if (! Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }
};
