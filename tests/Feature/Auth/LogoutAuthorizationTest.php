<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Support\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 登出授權：只能讓「已驗證身分」的 token 失效，client 送來的 user 一律不信任。
 *
 * VerifyApi 在 runningUnitTests() 時會直接塞假使用者放行，
 * 這裡把 app env 暫時切成非 testing，讓請求真正走過 middleware 的驗證邏輯。
 * 只影響測試行程，不改任何正式環境程式碼。
 */
class LogoutAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACKER_TOKEN = 'attacker-member-token';

    private const VICTIM_TOKEN = 'victim-member-token';

    private int $attackerId;

    private int $victimId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $this->attackerId = $this->createUser('attacker@example.com', self::ATTACKER_TOKEN);
        $this->victimId = $this->createUser('victim@example.com', self::VICTIM_TOKEN);

        // 關掉 VerifyApi 的 runningUnitTests 捷徑
        $this->app['env'] = 'local';
        $this->assertFalse($this->app->runningUnitTests());
    }

    public function test_without_credentials_forged_user_id_is_noop(): void
    {
        $this->postJson('/api/auth/logout', ['user' => ['id' => $this->victimId]])
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);

        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
        $this->assertSame(self::ATTACKER_TOKEN, $this->tokenOf($this->attackerId));
    }

    public function test_without_credentials_forged_user_id_in_query_string_is_noop(): void
    {
        $this->post('/api/auth/logout?user[id]='.$this->victimId)
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);

        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
    }

    public function test_valid_member_token_with_forged_user_id_only_logs_out_self(): void
    {
        $this->postJson('/api/auth/logout', [
            'member_token' => self::ATTACKER_TOKEN,
            'user' => ['id' => $this->victimId],
        ])->assertOk()->assertJson(['status' => true, 'code' => 200]);

        $this->assertNotSame(self::ATTACKER_TOKEN, $this->tokenOf($this->attackerId));
        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
    }

    public function test_valid_bearer_jwt_with_forged_user_id_only_logs_out_self(): void
    {
        $jwt = app(JwtTokenService::class)->makeUserJwt([
            'id' => $this->attackerId,
            'account' => 'attacker@example.com',
            'name' => 'attacker',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->postJson('/api/auth/logout', ['user' => ['id' => $this->victimId]])
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);

        $this->assertNotSame(self::ATTACKER_TOKEN, $this->tokenOf($this->attackerId));
        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
    }

    public function test_invalid_member_token_is_noop(): void
    {
        $this->postJson('/api/auth/logout', [
            'member_token' => 'not-a-real-token',
            'user' => ['id' => $this->victimId],
        ])->assertOk()->assertJson(['status' => true, 'code' => 200]);

        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
        $this->assertSame(self::ATTACKER_TOKEN, $this->tokenOf($this->attackerId));
    }

    public function test_invalid_bearer_jwt_is_noop(): void
    {
        $this->withHeader('Authorization', 'Bearer not.a.jwt')
            ->postJson('/api/auth/logout', ['user' => ['id' => $this->victimId]])
            ->assertOk()
            ->assertJson(['status' => true, 'code' => 200]);

        $this->assertSame(self::VICTIM_TOKEN, $this->tokenOf($this->victimId));
        $this->assertSame(self::ATTACKER_TOKEN, $this->tokenOf($this->attackerId));
    }

    public function test_other_verify_api_routes_still_require_credentials(): void
    {
        $this->postJson('/api/auth/user/thirdParty', ['user' => ['id' => $this->victimId]])
            ->assertStatus(401)
            ->assertJson(['status' => false, 'code' => 401]);

        $this->withHeader('Authorization', 'Bearer not.a.jwt')
            ->postJson('/api/auth/user/thirdParty')
            ->assertStatus(401);
    }

    private function createUser(string $account, string $token): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $account,
            'account' => $account,
            'password' => 'x',
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tokenOf(int $userId): string
    {
        return (string) DB::table('users')->where('id', $userId)->value('token');
    }
}
