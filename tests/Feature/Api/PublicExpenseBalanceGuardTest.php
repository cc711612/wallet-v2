<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-12：update 公費支出時，餘額檢查要排除該筆明細自身的舊值（sqlite 整合測試，fake repo 驗不出）。
 */
class PublicExpenseBalanceGuardTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private string $jwt;

    private int $expenseId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $user = $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
        $this->jwt = $this->userJwt($user);

        $this->storePublic(SymbolOperationType::INCREMENT->value, 100)->assertJsonPath('code', 200);
        $this->storePublic(SymbolOperationType::DECREMENT->value, 80)->assertJsonPath('code', 200);
        $this->expenseId = (int) DB::table('wallet_details')->where('value', 80)->value('id');
    }

    public function test_update_within_balance_is_allowed(): void
    {
        $this->updatePublic(90)->assertJsonPath('code', 200);

        $this->assertEquals(90, DB::table('wallet_details')->where('id', $this->expenseId)->value('value'));
    }

    public function test_update_exceeding_balance_is_rejected(): void
    {
        $this->updatePublic(120)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '公費結算金額不得為負數');
    }

    private function storePublic(int $operation, int $value): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->jwt)->postJson('/api/wallet/1/detail', $this->payload($operation, $value));
    }

    private function updatePublic(int $value): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->jwt)->putJson('/api/wallet/1/detail/'.$this->expenseId, $this->payload(SymbolOperationType::DECREMENT->value, $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $operation, int $value): array
    {
        return [
            'type' => WalletDetailType::PUBLIC_EXPENSE->value,
            'symbol_operation_type_id' => $operation,
            'title' => '公費',
            'value' => $value,
            'select_all' => true,
        ];
    }
}
