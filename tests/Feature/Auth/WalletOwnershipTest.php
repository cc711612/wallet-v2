<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-4：JWT 路徑必須檢查路由上的 {wallet} 是否為自己所屬帳本；建立者不得由 client 指定。
 */
class WalletOwnershipTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $userA;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->userA = $this->createUser(1, 'alice');
        $this->createUser(2, 'bob');
        $this->createWallet(1, 1);
        $this->createWallet(2, 2);
        $this->createWalletUser(10, 1, 1, true);
        $this->createWalletUser(20, 2, 2, true);
    }

    public function test_jwt_cannot_read_other_users_wallet_details(): void
    {
        $this->withToken($this->userJwt($this->userA))
            ->getJson('/api/wallet/2/detail')
            ->assertStatus(403);
    }

    public function test_jwt_cannot_write_to_other_users_wallet_even_with_wallet_user_id(): void
    {
        $this->withToken($this->userJwt($this->userA))
            ->postJson('/api/wallet/2/detail', $this->detailPayload(['wallet_user_id' => 20, 'users' => [20]]))
            ->assertStatus(403);

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_jwt_cannot_read_calculation_of_other_wallet(): void
    {
        $this->withToken($this->userJwt($this->userA))
            ->postJson('/api/wallet/2/calculation')
            ->assertStatus(403);
    }

    public function test_jwt_can_access_own_wallet(): void
    {
        $this->withToken($this->userJwt($this->userA))
            ->getJson('/api/wallet/1/detail')
            ->assertOk();
    }

    public function test_client_supplied_wallet_user_id_is_ignored_on_store(): void
    {
        $this->withToken($this->userJwt($this->userA))
            ->postJson('/api/wallet/1/detail', $this->detailPayload(['wallet_user_id' => 20, 'users' => [10]]))
            ->assertOk();

        $detail = DB::table('wallet_details')->first();
        $this->assertNotNull($detail);
        $this->assertSame(10, (int) $detail->created_by, '建立者應為 middleware 驗證過的帳本成員，而非 client 帶的 wallet_user_id');
    }

    public function test_member_token_of_other_wallet_is_rejected(): void
    {
        $this->getJson('/api/wallet/1/detail?member_token=member-token-20')->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function detailPayload(array $override = []): array
    {
        return array_merge([
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => 'Dinner',
            'value' => 100,
            'select_all' => false,
            'users' => [10],
        ], $override);
    }
}
