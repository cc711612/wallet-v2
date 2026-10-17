<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\JwtTokenService;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

class JwtTokenServiceTest extends TestCase
{
    use BuildsAuthFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_token_issued_by_service_is_verified(): void
    {
        $service = new JwtTokenService;
        $jwt = $service->makeUserJwt(['id' => 1, 'account' => 'roy', 'name' => 'Roy']);

        $payload = $service->decodeVerified($jwt);

        $this->assertIsArray($payload);
        $this->assertSame('roy', $payload['user']['account']);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $service = new JwtTokenService;
        [$header, , $signature] = explode('.', $service->makeUserJwt(['id' => 1, 'account' => 'roy']));
        $forgedPayload = rtrim(strtr(base64_encode((string) json_encode(['user' => ['account' => 'admin'], 'exp' => time() + 3600])), '+/', '-_'), '=');

        $this->assertNull($service->decodeVerified($header.'.'.$forgedPayload.'.'.$signature));
    }

    public function test_wrong_key_is_rejected(): void
    {
        $jwt = $this->craftJwt(['user' => ['id' => 'x'], 'exp' => time() + 3600], 'attacker-key');

        $this->assertNull((new JwtTokenService)->decodeVerified($jwt));
    }

    public function test_unsigned_and_alg_none_tokens_are_rejected(): void
    {
        $service = new JwtTokenService;
        $payload = ['user' => ['id' => 'x'], 'exp' => time() + 3600];

        $this->assertNull($service->decodeVerified($this->craftJwt($payload)));
        $this->assertNull($service->decodeVerified($this->craftJwt($payload, null, ['typ' => 'JWT', 'alg' => 'none'])));
        $this->assertNull($service->decodeVerified($this->craftJwt($payload, (string) config('app.name'), ['typ' => 'JWT', 'alg' => 'HS512'])));
        $this->assertNull($service->decodeVerified('not-a-jwt'));
    }

    public function test_expired_token_is_rejected(): void
    {
        $service = new JwtTokenService;
        $jwt = $service->makeUserJwt(['id' => 1]);

        Carbon::setTestNow(now()->addYears(2));

        $this->assertNull($service->decodeVerified($jwt));
    }

    public function test_token_without_exp_is_rejected(): void
    {
        $jwt = $this->craftJwt(['user' => ['id' => 'x']], (string) config('app.name'));

        $this->assertNull((new JwtTokenService)->decodeVerified($jwt));
    }

    public function test_not_yet_valid_token_is_rejected(): void
    {
        $jwt = $this->craftJwt(['user' => ['id' => 'x'], 'exp' => time() + 7200, 'nbf' => time() + 3600], (string) config('app.name'));

        $this->assertNull((new JwtTokenService)->decodeVerified($jwt));
    }

    public function test_jwt_secret_config_is_used_when_set(): void
    {
        $service = new JwtTokenService;
        $legacy = $service->makeUserJwt(['id' => 1]);

        config(['services.jwt.secret' => 'dedicated-secret']);
        $fresh = $service->makeUserJwt(['id' => 1]);

        $this->assertNotNull($service->decodeVerified($fresh));
        $this->assertNull($service->decodeVerified($legacy), '設定 JWT_SECRET 後，以 APP_NAME 簽發的舊 token 應失效');
    }
}
