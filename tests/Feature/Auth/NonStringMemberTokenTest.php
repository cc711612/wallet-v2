<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * member_token 不是字串（例如 member_token[]=x、JSON 陣列／物件／數字）時，
 * 一律視為沒有有效憑證：VerifyApi／VerifyWalletMemberApi 回 401，登出維持 200 no-op，不可 500。
 */
class NonStringMemberTokenTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_verify_api_rejects_array_member_token_in_query_with_401(): void
    {
        $this->createUser(1, 'roy', 'valid-user-token');

        $this->getJson('/api/wallet?member_token[]=valid-user-token')
            ->assertStatus(401)
            ->assertJson(['status' => false, 'code' => 401]);
    }

    public function test_verify_api_rejects_non_string_member_token_in_json_body_with_401(): void
    {
        $this->createUser(1, 'roy', 'valid-user-token');

        foreach ([['valid-user-token'], ['a' => 'valid-user-token'], 12345, true] as $token) {
            $this->postJson('/api/wallet', ['member_token' => $token, 'title' => 'x'])
                ->assertStatus(401)
                ->assertJson(['status' => false, 'code' => 401]);
        }
    }

    public function test_wallet_member_api_rejects_array_member_token_with_401(): void
    {
        $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, false, 'valid-member-token');

        $this->getJson('/api/wallet/1/detail?member_token[]=valid-member-token')
            ->assertStatus(401)
            ->assertJson(['status' => false, 'code' => 401]);

        $this->postJson('/api/wallet/1/detail', ['member_token' => ['valid-member-token']])
            ->assertStatus(401)
            ->assertJson(['status' => false, 'code' => 401]);
    }

    public function test_logout_with_array_member_token_is_noop_200(): void
    {
        $this->createUser(1, 'roy', 'valid-user-token');

        $this->postJson('/api/auth/logout', ['member_token' => ['valid-user-token']])
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);

        $this->post('/api/auth/logout?member_token[]=valid-user-token')->assertOk();

        $this->assertSame('valid-user-token', DB::table('users')->where('id', 1)->value('token'));
    }

    public function test_string_member_token_still_works(): void
    {
        $this->createUser(1, 'roy', 'valid-user-token');

        $this->getJson('/api/wallet?member_token=valid-user-token')->assertOk();
    }
}
