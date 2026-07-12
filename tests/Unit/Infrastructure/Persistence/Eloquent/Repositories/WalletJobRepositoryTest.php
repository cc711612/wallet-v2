<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletJobRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 注意：本測試刻意不使用 RefreshDatabase / 完整 migrate，
 * 因為現有 migration 集合本身有既存的 duplicate index 問題
 * （在此改動前，tests/Unit/Infrastructure/Persistence/Eloquent/Repositories/LineWebhookJobRepositoryTest.php
 * 這個既有測試單獨執行也會因同樣原因失敗，屬於既有環境問題，非本次改動引入）。
 * 因此這裡只手動建立本測試需要的三張表，避免觸發該既有問題。
 */
class WalletJobRepositoryTest extends TestCase
{
    private WalletJobRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new WalletJobRepository;

        Schema::create('wallets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('title')->nullable();
            $table->string('code')->nullable();
            $table->smallInteger('status')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wallet_users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->smallInteger('is_admin')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wallet_details', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->smallInteger('type')->default(2);
            $table->unsignedBigInteger('payment_wallet_user_id')->nullable();
            $table->string('title');
            $table->smallInteger('symbol_operation_type_id')->default(2);
            $table->smallInteger('select_all')->default(0);
            $table->float('value')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wallet_detail_wallet_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('wallet_user_id');
            $table->unsignedBigInteger('wallet_detail_id');
            $table->unique(['wallet_user_id', 'wallet_detail_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('wallet_detail_wallet_user');
        Schema::dropIfExists('wallet_details');
        Schema::dropIfExists('wallet_users');
        Schema::dropIfExists('wallets');

        parent::tearDown();
    }

    public function test_batch_sync_attaches_all_combinations_without_detaching_existing(): void
    {
        $wallet = WalletEntity::create(['title' => 'Trip', 'code' => 'ABC123', 'status' => 1]);

        $walletUserA = WalletUserEntity::create(['wallet_id' => $wallet->id, 'name' => 'A']);
        $walletUserB = WalletUserEntity::create(['wallet_id' => $wallet->id, 'name' => 'B']);
        $walletUserC = WalletUserEntity::create(['wallet_id' => $wallet->id, 'name' => 'C']);

        $detail1 = WalletDetailEntity::create([
            'wallet_id' => $wallet->id,
            'type' => 2,
            'title' => 'Detail 1',
            'select_all' => 1,
            'value' => 100,
        ]);
        $detail2 = WalletDetailEntity::create([
            'wallet_id' => $wallet->id,
            'type' => 2,
            'title' => 'Detail 2',
            'select_all' => 1,
            'value' => 200,
        ]);

        // 先手動建立一筆既有關聯，驗證 batch sync 不會移除它。
        $detail1->users()->attach($walletUserA->id);

        $this->repository->syncDetailUsersWithoutDetachingBatch(
            [$detail1->id, $detail2->id],
            [$walletUserA->id, $walletUserB->id, $walletUserC->id],
        );

        $detail1UserIds = $detail1->users()->pluck('wallet_users.id')->sort()->values()->all();
        $detail2UserIds = $detail2->users()->pluck('wallet_users.id')->sort()->values()->all();

        $this->assertSame(
            [$walletUserA->id, $walletUserB->id, $walletUserC->id],
            $detail1UserIds
        );
        $this->assertSame(
            [$walletUserA->id, $walletUserB->id, $walletUserC->id],
            $detail2UserIds
        );
    }

    public function test_batch_sync_is_a_no_op_when_detail_ids_or_wallet_user_ids_are_empty(): void
    {
        // 不應丟例外，也不應寫入任何 row。
        $this->repository->syncDetailUsersWithoutDetachingBatch([], [1, 2]);
        $this->repository->syncDetailUsersWithoutDetachingBatch([1, 2], []);

        $this->assertDatabaseCount('wallet_detail_wallet_user', 0);
    }
}
