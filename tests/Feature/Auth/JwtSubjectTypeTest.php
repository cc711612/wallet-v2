<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Support\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 驗收後修正 2：JWT 內的加密 id 帶型別前綴（user:／wallet_user:），
 * 不同型別的密文不可互換使用，沒有前綴的舊密文一律拒絕。
 */
class JwtSubjectTypeTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // user #5 與 wallet_user #5 同號：攻擊者是 user #5，受害者是帳本 2 的成員 wallet_user #5
        $this->createUser(5, 'attacker');
        $this->createUser(6, 'victim');
        $this->createWallet(1, 5);
        $this->createWallet(2, 6);
        $this->createWalletUser(5, 2, null, true);
        $this->createWalletUser(50, 1, 5, true);
    }

    public function test_user_ciphertext_moved_into_wallet_user_is_rejected(): void
    {
        $legit = app(JwtTokenService::class)->makeUserJwt(['id' => 5, 'account' => 'attacker']);
        $cipher = (string) $this->payloadOf($legit)['user']['id'];

        $forged = $this->craftJwt(['exp' => time() + 3600, 'wallet_user' => ['id' => $cipher]], (string) config('services.jwt.secret'));

        $this->withToken($forged)->getJson('/api/wallet/2/detail')->assertStatus(401);
    }

    public function test_wallet_user_ciphertext_moved_into_user_is_rejected(): void
    {
        $legit = app(JwtTokenService::class)->makeWalletUserJwt(['id' => 5, 'name' => 'member']);
        $cipher = (string) $this->payloadOf($legit)['wallet_user']['id'];

        $forged = $this->craftJwt(['exp' => time() + 3600, 'user' => ['id' => $cipher]], (string) config('services.jwt.secret'));

        $this->withToken($forged)->getJson('/api/wallet')->assertStatus(401);
        $this->withToken($forged)->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_untyped_legacy_ciphertext_is_rejected(): void
    {
        $secret = (string) config('services.jwt.secret');

        $this->withToken($this->craftJwt(['exp' => time() + 3600, 'user' => ['id' => Crypt::encryptString('5')]], $secret))
            ->getJson('/api/wallet')->assertStatus(401);
        $this->withToken($this->craftJwt(['exp' => time() + 3600, 'wallet_user' => ['id' => Crypt::encryptString('5')]], $secret))
            ->getJson('/api/wallet/2/detail')->assertStatus(401);
    }

    public function test_bind_flow_rejects_user_ciphertext_as_wallet_user(): void
    {
        // 攻擊者以自己的 user 密文冒充邀請中的 wallet_user #5，試圖綁到自己帳號
        $legit = app(JwtTokenService::class)->makeUserJwt(['id' => 5, 'account' => 'attacker']);
        $cipher = (string) $this->payloadOf($legit)['user']['id'];
        $forged = $this->craftJwt(['exp' => time() + 3600, 'wallet_user' => ['id' => $cipher]], (string) config('services.jwt.secret'));

        $this->withToken($forged)->postJson('/api/auth/login', ['account' => 'attacker', 'password' => 'secret', 'type' => 'bind'])
            ->assertJsonPath('code', 400);

        $this->assertNull(DB::table('wallet_users')->where('id', 5)->value('user_id'));
    }

    public function test_bind_flow_accepts_genuine_wallet_user_token(): void
    {
        $jwt = app(JwtTokenService::class)->makeWalletUserJwt(['id' => 5, 'name' => 'member']);

        $this->withToken($jwt)->postJson('/api/auth/login', ['account' => 'attacker', 'password' => 'secret', 'type' => 'bind'])
            ->assertJsonPath('code', 200);

        $this->assertSame(5, (int) DB::table('wallet_users')->where('id', 5)->value('user_id'));
    }

    public function test_genuine_tokens_still_work(): void
    {
        $service = app(JwtTokenService::class);

        $this->withToken($service->makeUserJwt(['id' => 5]))->getJson('/api/wallet/1/detail')->assertOk();
        $this->withToken($service->makeWalletUserJwt(['id' => 5]))->getJson('/api/wallet/2/detail')->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadOf(string $jwt): array
    {
        return (array) json_decode((string) base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }
}
