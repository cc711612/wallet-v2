<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 登入不可被用來列舉帳號：帳號不存在與密碼錯誤，回應（HTTP status＋body）與處理成本都要一致。
 */
class LoginAccountEnumerationTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_missing_account_and_wrong_password_return_identical_response(): void
    {
        $this->createUser(1, 'roy');

        $wrongPassword = $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'wrong-pass']);
        $missingAccount = $this->postJson('/api/auth/login', ['account' => 'nobody', 'password' => 'wrong-pass']);

        $this->assertSame($wrongPassword->getStatusCode(), $missingAccount->getStatusCode());
        $this->assertSame($wrongPassword->getContent(), $missingAccount->getContent());
        $missingAccount->assertJson(['status' => false, 'code' => 400, 'message' => '帳號或密碼錯誤']);
    }

    public function test_account_without_password_returns_same_response_as_wrong_password(): void
    {
        $this->createUser(1, 'roy');
        $this->createUser(2, 'social-only');
        DB::table('users')->where('id', 2)->update(['password' => '']);

        $wrongPassword = $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'wrong-pass']);
        $noPassword = $this->postJson('/api/auth/login', ['account' => 'social-only', 'password' => 'wrong-pass']);

        $this->assertSame($wrongPassword->getStatusCode(), $noPassword->getStatusCode());
        $this->assertSame($wrongPassword->getContent(), $noPassword->getContent());
    }

    public function test_missing_account_still_performs_password_hash_check(): void
    {
        // 帳號不存在時若直接回錯、跳過雜湊比對，回應時間會明顯較短，仍可用時間差列舉帳號
        Hash::spy();

        $this->postJson('/api/auth/login', ['account' => 'nobody', 'password' => 'wrong-pass'])
            ->assertJson(['status' => false, 'code' => 400, 'message' => '帳號或密碼錯誤']);

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_login_still_succeeds_with_correct_password(): void
    {
        $this->createUser(1, 'roy');

        $this->postJson('/api/auth/login', ['account' => 'roy', 'password' => 'secret'])
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);
    }

    public function test_login_is_still_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['account' => 'nobody', 'password' => 'wrong-pass'])->assertOk();
        }

        $this->postJson('/api/auth/login', ['account' => 'nobody', 'password' => 'wrong-pass'])->assertStatus(429);
    }
}
