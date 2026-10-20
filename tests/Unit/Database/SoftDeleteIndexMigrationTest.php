<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-17：soft delete 欄位上的 UNIQUE(wallet_id, deleted_at) 改為一般 index。
 */
class SoftDeleteIndexMigrationTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unique_index_is_replaced_by_normal_index(): void
    {
        foreach (['wallet_details', 'wallet_users'] as $table) {
            $this->assertFalse(Schema::hasIndex($table, ['wallet_id', 'deleted_at'], 'unique'), $table);
            $this->assertTrue(Schema::hasIndex($table, ['wallet_id', 'deleted_at']), $table);
        }
    }

    public function test_soft_deleting_two_rows_in_same_second_does_not_collide(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
        $this->createWalletUser(11, 1, null);
        $this->createWalletUser(12, 1, null);

        foreach ([1, 2] as $id) {
            DB::table('wallet_details')->insert([
                'id' => $id, 'wallet_id' => 1, 'title' => 't'.$id, 'value' => 1, 'splits' => '[]',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        WalletDetailEntity::query()->whereKey(1)->firstOrFail()->delete();
        WalletDetailEntity::query()->whereKey(2)->firstOrFail()->delete();
        WalletUserEntity::query()->whereKey(11)->firstOrFail()->delete();
        WalletUserEntity::query()->whereKey(12)->firstOrFail()->delete();

        $this->assertSame(2, DB::table('wallet_details')->whereNotNull('deleted_at')->count());
        $this->assertSame(2, DB::table('wallet_users')->whereNotNull('deleted_at')->count());
    }
}
