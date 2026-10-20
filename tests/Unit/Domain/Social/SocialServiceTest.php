<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Social;

use App\Domain\Social\Enums\SocialTypeEnum;
use App\Domain\Social\Services\SocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-6：checkBind 必須向 provider 驗證 id_token，不能只信任 client 傳來的 social id。
 */
class SocialServiceTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const LINE = SocialTypeEnum::SOCIAL_TYPE_LINE->value;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social_login.verify_token' => true,
            'services.line_login.channel_id' => '1234567890',
            'services.google_login.client_ids' => ['google-client-id'],
        ]);

        $this->createUser(1, 'victim');
        DB::table('socials')->insert(['id' => 1, 'social_type' => self::LINE, 'social_type_value' => 'U-victim']);
        DB::table('user_social')->insert(['user_id' => 1, 'social_id' => 1]);
    }

    public function test_rejects_when_provider_says_token_is_invalid(): void
    {
        Http::fake(['api.line.me/*' => Http::response(['error' => 'invalid_request'], 400)]);

        $this->expectException(RuntimeException::class);

        app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim', 'id_token' => 'forged']);
    }

    public function test_rejects_when_id_token_is_missing(): void
    {
        Http::fake();

        try {
            app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim']);
            $this->fail('未帶 id_token 應被拒絕');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_rejects_when_verified_sub_differs_from_claimed_value(): void
    {
        Http::fake(['api.line.me/*' => Http::response(['sub' => 'U-attacker', 'aud' => '1234567890'])]);

        $this->expectException(RuntimeException::class);

        app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim', 'id_token' => 'attacker-own-token']);
    }

    public function test_rejects_when_audience_is_another_channel(): void
    {
        Http::fake(['api.line.me/*' => Http::response(['sub' => 'U-victim', 'aud' => 'other-channel'])]);

        $this->expectException(RuntimeException::class);

        app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim', 'id_token' => 'tk']);
    }

    public function test_rejects_unsupported_social_type(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        app(SocialService::class)->checkBind(['socialType' => SocialTypeEnum::SOCIAL_TYPE_EMAIL->value, 'socialTypeValue' => 'a@b.c', 'id_token' => 'tk']);
    }

    public function test_rejects_when_channel_id_not_configured(): void
    {
        config(['services.line_login.channel_id' => '']);
        Http::fake();

        try {
            app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim', 'id_token' => 'tk']);
            $this->fail('LINE channel id 未設定應 fail-closed');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_accepts_verified_line_token(): void
    {
        Http::fake(['api.line.me/*' => Http::response(['sub' => 'U-victim', 'aud' => '1234567890'])]);

        $result = app(SocialService::class)->checkBind(['socialType' => self::LINE, 'socialTypeValue' => 'U-victim', 'id_token' => 'valid']);

        $this->assertSame('bind', $result['action']);
        Http::assertSent(static fn ($request): bool => $request['id_token'] === 'valid' && $request['client_id'] === '1234567890');
    }

    public function test_accepts_verified_google_token(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'sub' => 'G-1', 'aud' => 'google-client-id', 'iss' => 'https://accounts.google.com', 'exp' => (string) (time() + 600),
        ])]);

        $result = app(SocialService::class)->checkBind(['socialType' => SocialTypeEnum::SOCIAL_TYPE_GOOGLE->value, 'socialTypeValue' => 'G-1', 'id_token' => 'valid']);

        $this->assertSame('not bind', $result['action']);
    }

    public function test_check_bind_endpoint_does_not_issue_login_token_for_forged_request(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 400)]);

        $response = $this->postJson('/api/auth/thirdParty/checkBind', [
            'socialType' => self::LINE,
            'socialTypeValue' => 'U-victim',
            'id_token' => 'forged',
        ]);

        $response->assertJsonPath('code', 400);
        $this->assertNull($response->json('data.member_token'));
        $this->assertNull($response->json('data.jwt'));
    }
}
