<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-5：帳本成員只能改自己；刪除成員必須是該帳本 admin。
 */
class WalletUserAuthorizationTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $alice;

    /** @var array<string, mixed> */
    private array $carol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->createUser(1, 'alice');
        $this->createUser(2, 'bob');
        $this->carol = $this->createUser(3, 'carol');
        $this->createWallet(1, 1);
        $this->createWallet(2, 2);
        $this->createWalletUser(10, 1, 1, true);
        $this->createWalletUser(11, 1, 3, false);
        $this->createWalletUser(20, 2, 2, true);
    }

    public function test_member_cannot_update_other_member(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->putJson('/api/wallet/user/20', ['name' => 'hacked'])
            ->assertStatus(403);

        $this->assertSame('member-20', DB::table('wallet_users')->where('id', 20)->value('name'));
    }

    public function test_member_can_update_self(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->putJson('/api/wallet/user/10', ['name' => 'Alice'])
            ->assertOk();

        $this->assertSame('Alice', DB::table('wallet_users')->where('id', 10)->value('name'));
    }

    public function test_member_token_of_non_first_wallet_can_update_self(): void
    {
        // 修正前 member_token 路徑在沒有 {wallet} 的路由一律以帳本 1 比對，帳本 2 的成員無法使用
        $this->putJson('/api/wallet/user/20', ['name' => 'Bob', 'member_token' => 'member-token-20'])
            ->assertOk();

        $this->assertSame('Bob', DB::table('wallet_users')->where('id', 20)->value('name'));
    }

    public function test_non_admin_cannot_delete_member(): void
    {
        $this->withToken($this->userJwt($this->carol))
            ->deleteJson('/api/wallet/1/user/10')
            ->assertStatus(403);

        $this->assertNull(DB::table('wallet_users')->where('id', 10)->value('deleted_at'));
    }

    public function test_admin_can_delete_member(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->deleteJson('/api/wallet/1/user/11')
            ->assertOk();

        $this->assertNotNull(DB::table('wallet_users')->where('id', 11)->value('deleted_at'));
    }

    public function test_admin_of_other_wallet_cannot_delete_member(): void
    {
        $bob = (array) DB::table('users')->where('id', 2)->first();

        $this->withToken($this->userJwt($bob))
            ->deleteJson('/api/wallet/1/user/11')
            ->assertStatus(403);
    }
}
