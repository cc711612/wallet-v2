<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-9：登入類端點限流；登入錯誤訊息不區分帳號是否存在。
 */
class RateLimitTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_password_login_is_throttled_per_ip_and_account(): void
    {
        $this->createUser(1, 'roy');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'wrong-pass'])->assertOk();
        }

        $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'wrong-pass'])->assertStatus(429);
    }

    public function test_wallet_code_login_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/wallet/auth/login', ['code' => 'NOPE', 'name' => 'x']);
        }

        $this->postJson('/api/wallet/auth/login', ['code' => 'NOPE', 'name' => 'x'])->assertStatus(429);
    }

    public function test_third_party_check_bind_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/thirdParty/checkBind', ['socialType' => 9, 'socialTypeValue' => 'U1']);
        }

        $this->postJson('/api/auth/thirdParty/checkBind', ['socialType' => 9, 'socialTypeValue' => 'U1'])->assertStatus(429);
    }

    public function test_front_log_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/log/front/normal', ['message' => 'x']);
        }

        $this->postJson('/api/log/front/normal', ['message' => 'x'])->assertStatus(429);
    }

    public function test_login_error_does_not_reveal_whether_account_exists(): void
    {
        $this->createUser(1, 'roy');

        $existing = $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'wrong-pass']);
        $missing = $this->postJson('/api/auth/login', ['account' => 'nobody', 'password' => 'wrong-pass']);

        $existing->assertJsonPath('message', '帳號或密碼錯誤');
        $missing->assertJsonPath('message', '帳號或密碼錯誤');
    }

    public function test_register_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', ['account' => 'u'.$i, 'password' => '123456', 'name' => 'n']);
        }

        $this->postJson('/api/auth/register', ['account' => 'u9', 'password' => '123456', 'name' => 'n'])->assertStatus(429);
    }

    public function test_register_by_token_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register/token', ['token' => 'tk'.$i]);
        }

        $this->postJson('/api/auth/register/token', ['token' => 'tk9'])->assertStatus(429);
    }

    public function test_wallet_guest_batch_register_is_throttled_regardless_of_code(): void
    {
        // 換不同帳本 code 也不能繞過（以 IP 計）
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/wallet/auth/register/batch', ['code' => 'C'.$i, 'name' => ['a']]);
        }

        $this->postJson('/api/wallet/auth/register/batch', ['code' => 'C9', 'name' => ['a']])->assertStatus(429);
    }
}
