<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 真實走 VerifyApi / VerifyWalletMemberApi（不使用 fakeApiAuthentication）。
 */
class MiddlewareAuthTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_verify_api_route_without_token_returns_401(): void
    {
        $this->getJson('/api/wallet')->assertStatus(401);
    }

    public function test_verify_api_route_with_unknown_member_token_returns_401(): void
    {
        $this->getJson('/api/wallet?member_token=not-exists')->assertStatus(401);
    }

    public function test_wallet_member_route_without_token_returns_401(): void
    {
        $this->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_wallet_member_route_with_unknown_member_token_returns_401(): void
    {
        $this->getJson('/api/wallet/1/detail?member_token=not-exists')->assertStatus(401);
    }

    public function test_valid_jwt_passes_verify_api(): void
    {
        $user = $this->createUser(1, 'roy');

        $this->withToken($this->userJwt($user))->getJson('/api/wallet')->assertOk();
    }

    public function test_forged_jwt_signed_with_wrong_key_returns_401(): void
    {
        $this->createUser(1, 'roy');
        $jwt = $this->craftJwt([
            'exp' => time() + 3600,
            'user' => ['id' => Crypt::encryptString('1'), 'account' => 'roy'],
        ], 'attacker-key');

        $this->withToken($jwt)->getJson('/api/wallet')->assertStatus(401);
        $this->withToken($jwt)->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_unsigned_jwt_returns_401(): void
    {
        $this->createUser(1, 'roy');
        $jwt = $this->craftJwt(['exp' => time() + 3600, 'user' => ['account' => 'roy']], null, ['typ' => 'JWT', 'alg' => 'none']);

        $this->withToken($jwt)->getJson('/api/wallet')->assertStatus(401);
        $this->withToken($jwt)->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_jwt_with_only_account_cannot_login_even_if_signed(): void
    {
        // 簽章金鑰沿用 APP_NAME（非秘密），因此即使簽章正確，只帶 account 也不能登入
        $user = $this->createUser(1, 'roy');
        $this->createWallet(1, 1);
        $this->createWalletUser(100, 1, (int) $user['id'], true);
        $jwt = $this->craftJwt(['exp' => time() + 3600, 'user' => ['account' => 'roy']], (string) config('app.name'));

        $this->withToken($jwt)->getJson('/api/wallet')->assertStatus(401);
        $this->withToken($jwt)->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_jwt_with_undecryptable_id_and_account_fallback_returns_401(): void
    {
        $this->createUser(1, 'roy');
        $jwt = $this->craftJwt(['exp' => time() + 3600, 'user' => ['id' => 'garbage', 'account' => 'roy']], (string) config('app.name'));

        $this->withToken($jwt)->getJson('/api/wallet')->assertStatus(401);
    }

    public function test_expired_jwt_returns_401(): void
    {
        $user = $this->createUser(1, 'roy');
        $jwt = $this->craftJwt([
            'exp' => time() - 3600,
            'user' => ['id' => Crypt::encryptString((string) $user['id'])],
        ], (string) config('app.name'));

        $this->withToken($jwt)->getJson('/api/wallet')->assertStatus(401);
    }
}
