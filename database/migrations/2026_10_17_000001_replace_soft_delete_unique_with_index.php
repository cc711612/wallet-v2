<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * wallet_details、wallet_users 的 UNIQUE(wallet_id, deleted_at) 搭配 SoftDeletes：
     * 同一帳本同一秒刪兩筆就會 duplicate key。改為一般 index。
     *
     * 先建新 index 再刪 UNIQUE：wallet_id 有 foreign key，MySQL 需要以 wallet_id 開頭的 index。
     * 只處理「確實是 UNIQUE」的情況，正式環境若已不是 UNIQUE 則略過。
     *
     * @var array<int, string>
     */
    private array $tables = ['wallet_details', 'wallet_users'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $unique = $this->findIndex($table, true);
            if ($unique === null) {
                continue;
            }

            $indexName = $table.'_wallet_id_deleted_at_index';
            Schema::table($table, function (Blueprint $blueprint) use ($indexName, $unique): void {
                $blueprint->index(['wallet_id', 'deleted_at'], $indexName);
                $blueprint->dropUnique($unique);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $indexName = $table.'_wallet_id_deleted_at_index';
            if (! Schema::hasIndex($table, $indexName)) {
                continue;
            }

            // 還原為原 migration 的名稱（SQLite 索引名稱全域唯一，沿用原 migration 的區分方式）
            $uniqueName = Schema::getConnection()->getDriverName() === 'sqlite'
                ? $table.'_wallet_id_deleted_at_unique'
                : 'index-wallet_id-deleted_at';

            Schema::table($table, function (Blueprint $blueprint) use ($indexName, $uniqueName): void {
                $blueprint->unique(['wallet_id', 'deleted_at'], $uniqueName);
                $blueprint->dropIndex($indexName);
            });
        }
    }

    private function findIndex(string $table, bool $unique): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === ['wallet_id', 'deleted_at'] && (bool) $index['unique'] === $unique && ! $index['primary']) {
                return (string) $index['name'];
            }
        }

        return null;
    }
};
