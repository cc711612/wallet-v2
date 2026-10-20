<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Domain\Notification\Services\NotificationJobService;
use App\Domain\Wallet\Entities\WalletDetailEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-15：新增明細的推播要在 transaction commit 之後才送出。
 * 使用 sync queue（phpunit.xml），QueueFake 不模擬 afterCommit，因此改以 mock 推播 service 觀察。
 */
class WalletDetailObserverTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
        DB::table('wallet_users')->where('id', 10)->update(['notify_enable' => 1]);
    }

    public function test_notification_is_not_sent_when_transaction_rolls_back(): void
    {
        $this->mock(NotificationJobService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendFcm');
        });

        try {
            DB::transaction(function (): void {
                $this->createDetail();
                $this->assertSame(1, DB::table('wallet_details')->count());

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_notification_is_sent_only_after_commit(): void
    {
        $sent = 0;
        $this->mock(NotificationJobService::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendFcm')->once()->andReturnUsing(function () use (&$sent): void {
                $sent++;
            });
        });

        DB::transaction(function () use (&$sent): void {
            $this->createDetail();

            $this->assertSame(0, $sent, 'transaction 尚未 commit 前不應推播');
        });

        $this->assertSame(1, $sent);
    }

    private function createDetail(): void
    {
        WalletDetailEntity::query()->create([
            'wallet_id' => 1,
            'type' => 2,
            'symbol_operation_type_id' => 2,
            'title' => 'Dinner',
            'value' => 100,
            'select_all' => false,
            'is_personal' => false,
            'unit' => 'TWD',
            'date' => '2026-10-17',
            'created_by' => 10,
            'updated_by' => 10,
            'splits' => [],
        ]);
    }
}
