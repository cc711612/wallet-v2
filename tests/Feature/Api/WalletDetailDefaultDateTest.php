<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-11：台灣時間 00:00–08:00 建立的明細，預設日期不可記到前一天（UTC）。
 */
class WalletDetailDefaultDateTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-17 01:00:00', 'Asia/Taipei'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_store_without_date_uses_taipei_today(): void
    {
        Queue::fake();
        $user = $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);

        $this->withToken($this->userJwt($user))->postJson('/api/wallet/1/detail', [
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => '宵夜',
            'value' => 100,
            'select_all' => false,
            'users' => [10],
        ])->assertOk();

        $this->assertSame('2026-10-17', (string) DB::table('wallet_details')->value('date'));
    }

    public function test_entity_default_date_uses_taipei_today(): void
    {
        $detail = WalletDetail::fromPayload([
            'wallet' => 1,
            'wallet_user_id' => 10,
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => '宵夜',
            'value' => 100,
        ]);

        $this->assertSame('2026-10-17', $detail->toPersistenceAttributes()['date']);
    }
}
