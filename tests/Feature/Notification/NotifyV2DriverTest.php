<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Exceptions\NotifyV2RetryableException;
use App\Domain\Notification\Services\NotificationJobService;
use App\Domain\Notification\Services\NotifyV2Service;
use App\Jobs\NotificationFCM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * NOTIFICATION_DRIVER=v2：改呼叫 notify POST /api/v2/messages。
 */
class NotifyV2DriverTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const V2_URL = 'https://notify-v2.example.test/api/v2/messages';

    private string $tokenA;

    private string $tokenB;

    private string $tokenExpired;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.notification.driver' => 'v2',
            'services.notification.url' => 'https://notify-v1.example.test',
            'services.notification.key' => 'v1-test-key',
            'services.notification.retry_times' => 1,
            'services.notification.retry_delay_ms' => 0,
            'services.notify_v2.url' => 'https://notify-v2.example.test/',
            'services.notify_v2.token' => 'fake-notify-token-for-tests',
            'services.easysplit.app_url' => 'https://easysplit.example.test',
            'services.easysplit.logo_path' => '/images/logo.png',
            'services.easysplit.frame_icon_path' => '/assets/images/Frame.svg',
            'services.easysplit.bot_id' => 'Easysplit-App',
        ]);

        $this->tokenA = 'fcmA:'.str_repeat('a', 60);
        $this->tokenB = 'fcmB:'.str_repeat('b', 60);
        $this->tokenExpired = 'fcmX:'.str_repeat('x', 60);

        $this->createUser(1, 'roy');
        $this->createWallet(5, 1);
        $this->createWalletUser(10, 5, 1, true);
        DB::table('wallet_users')->where('id', 10)->update(['notify_enable' => 1]);

        $this->createDevice(1, $this->tokenA, now()->addDays(30));
        $this->createDevice(2, $this->tokenB, now()->addDays(30));
        $this->createDevice(3, $this->tokenExpired, now()->subDay());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_v2_payload_shape_has_no_private_key_and_string_data(): void
    {
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        $this->runJob(new NotificationFCM(1001, 10, "有一筆新的記帳資料\r\n記帳金額：100"));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame(self::V2_URL, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame(['Bearer fake-notify-token-for-tests'], $request->header('Authorization'));
            $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0] ?? '');
            $this->assertSame([], $request->header('X-API-KEY'));

            $this->assertSame(['idempotency_key', 'tokens', 'notification', 'data', 'webpush'], array_keys($body));
            foreach (['platformParameters', 'platform', 'platformBotId', 'webhookUrl', 'targetId', 'users'] as $legacyKey) {
                $this->assertArrayNotHasKey($legacyKey, $body);
            }
            $this->assertStringNotContainsString('private_key', $request->body());

            $this->assertSame('wallet-detail-1001-wu-10', $body['idempotency_key']);
            $this->assertSame([$this->tokenA, $this->tokenB], $body['tokens']);
            $this->assertSame(['title' => 'Easysplit', 'body' => "有一筆新的記帳資料\r\n記帳金額：100"], $body['notification']);

            $this->assertSame([
                'wallet_id' => '5',
                'ledger_code' => 'W0000005',
                'type' => 'wallet_record_created',
                'link' => 'https://easysplit.example.test/ledger/W0000005/detail',
                'icon' => 'https://easysplit.example.test/assets/images/Frame.svg',
            ], $body['data']);
            foreach ($body['data'] as $value) {
                $this->assertIsString($value);
            }
            $this->assertSame([
                'link' => $body['data']['link'],
                'icon' => $body['data']['icon'],
            ], $body['webpush']);

            return true;
        });
    }

    public function test_invalid_tokens_expire_matching_devices_and_warning_is_logged(): void
    {
        Log::spy();
        Http::fake([self::V2_URL => Http::response($this->okBody([
            'status' => 'partial',
            'sent' => 1,
            'failed' => 1,
            'invalid_tokens' => [$this->tokenB],
            'warning' => ['code' => 'result_not_recorded', 'message' => 'finish failed'],
        ]))]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        $this->assertTrue(now()->lt(DB::table('devices')->where('id', 1)->value('expired_at')), 'tokenA 不應過期');
        $this->assertFalse(now()->lt(DB::table('devices')->where('id', 2)->value('expired_at')), 'invalid token 的裝置要設為過期');

        Log::shouldHaveReceived('warning')->with('NotifyV2Service response warning', Mockery::on(
            fn (array $context): bool => $context['code'] === 'result_not_recorded' && $context['idempotency_key'] === 'wallet-detail-1001-wu-10'
        ))->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_409_is_logged_and_not_retried(): void
    {
        Log::spy();
        Http::fake([self::V2_URL => Http::response(['error' => ['code' => 'idempotency_conflict', 'message' => 'conflict']], 409)]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->with('NotifyV2Service idempotency conflict, not retrying', Mockery::on(
            fn (array $context): bool => $context['status'] === 409 && $context['code'] === 'idempotency_conflict'
        ))->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_503_throws_for_job_retry_and_retry_reuses_same_key(): void
    {
        Http::fake([self::V2_URL => Http::sequence()
            ->push(['error' => ['code' => 'deadline_exceeded', 'message' => 'deadline']], 503)
            ->push($this->okBody())]);

        $job = new NotificationFCM(1001, 10, 'hello');

        try {
            $this->runJob($job);
            $this->fail('503 應丟出可重試例外，讓 queue job 重試');
        } catch (NotifyV2RetryableException $exception) {
            $this->assertStringContainsString('deadline_exceeded', $exception->getMessage());
        }

        // queue 重試時拿到的是同一份序列化 payload
        $this->runJob(unserialize(serialize($job)));

        $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->data()['idempotency_key'])->all();
        $this->assertSame(['wallet-detail-1001-wu-10', 'wallet-detail-1001-wu-10'], $keys);
    }

    public function test_connection_error_is_retryable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(NotifyV2RetryableException::class);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));
    }

    public function test_401_logs_error_and_does_not_retry(): void
    {
        Log::spy();
        Http::fake([self::V2_URL => Http::response(['error' => ['code' => 'unauthorized', 'message' => 'unauthorized']], 401)]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->with('NotifyV2Service request rejected', Mockery::on(
            fn (array $context): bool => $context['status'] === 401 && $context['code'] === 'unauthorized'
        ))->once();
    }

    /**
     * @return array<string, array{int, string, bool}>
     */
    public static function statusMatrix(): array
    {
        return [
            '400 invalid_request' => [400, 'invalid_request', false],
            '403 app_disabled' => [403, 'app_disabled', false],
            '422 credentials_not_configured' => [422, 'credentials_not_configured', false],
            '409 idempotency_unrecorded' => [409, 'idempotency_unrecorded', false],
            '429 rate_limited without Retry-After' => [429, 'rate_limited', true],
            '502 provider_unavailable' => [502, 'provider_unavailable', true],
            '503 unavailable' => [503, 'unavailable', true],
        ];
    }

    #[DataProvider('statusMatrix')]
    public function test_status_retry_matrix(int $status, string $code, bool $retryable): void
    {
        Log::spy();
        Http::fake([self::V2_URL => Http::response(['error' => ['code' => $code, 'message' => $code]], $status)]);

        $thrown = false;
        try {
            $this->runJob(new NotificationFCM(1001, 10, 'hello'));
        } catch (NotifyV2RetryableException) {
            $thrown = true;
        }

        $this->assertSame($retryable, $thrown);
        Http::assertSentCount(1);
        if (! $retryable && $status !== 409) {
            Log::shouldHaveReceived('error')->with('NotifyV2Service request rejected', Mockery::on(
                fn (array $context): bool => $context['status'] === $status && $context['code'] === $code
            ))->once();
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidWebpushAppUrls(): array
    {
        return [
            'http' => ['http://easysplit.example.test'],
            'relative (app_url empty)' => [''],
        ];
    }

    #[DataProvider('invalidWebpushAppUrls')]
    public function test_non_https_webpush_urls_are_not_sent(string $appUrl): void
    {
        Log::spy();
        config(['services.easysplit.app_url' => $appUrl]);
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($appUrl): bool {
            $body = $request->data();
            $this->assertArrayNotHasKey('webpush', $body);
            // data 仍照傳（只是字串，不受 https 限制）
            $this->assertSame($appUrl.'/ledger/W0000005/detail', $body['data']['link']);

            return true;
        });
        Log::shouldHaveReceived('warning')->with('NotifyV2Service dropped invalid webpush url', Mockery::any())->twice();
    }

    public function test_https_link_is_kept_when_only_icon_is_invalid(): void
    {
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        app(NotifyV2Service::class)->send('wallet-detail-1-wu-1', [$this->tokenA], 'Easysplit', 'hello', [
            'link' => 'https://easysplit.example.test/ledger/X/detail',
            'icon' => 'https:///no-host.svg',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->data()['webpush'] === ['link' => 'https://easysplit.example.test/ledger/X/detail']);
    }

    public function test_long_body_and_title_are_truncated_to_valid_utf8(): void
    {
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        app(NotifyV2Service::class)->send('wallet-detail-1-wu-1', [$this->tokenA], str_repeat('標', 500), 'a'.str_repeat('記', 2000), []);

        Http::assertSent(function (Request $request): bool {
            $notification = $request->data()['notification'];

            $this->assertLessThanOrEqual(4000, strlen($notification['body']));
            $this->assertGreaterThan(3900, strlen($notification['body']));
            $this->assertTrue(mb_check_encoding($notification['body'], 'UTF-8'));
            $this->assertStringStartsWith('a記記', $notification['body']);
            $this->assertStringEndsWith('…', $notification['body']);

            $this->assertLessThanOrEqual(1024, strlen($notification['title']));
            $this->assertTrue(mb_check_encoding($notification['title'], 'UTF-8'));
            $this->assertStringEndsWith('…', $notification['title']);

            return true;
        });
    }

    public function test_short_body_is_not_truncated(): void
    {
        Http::fake([self::V2_URL => Http::response($this->okBody())]);
        $body = str_repeat('記', 1333); // 3999 bytes

        app(NotifyV2Service::class)->send('wallet-detail-1-wu-1', [$this->tokenA], 'Easysplit', $body, []);

        Http::assertSent(fn (Request $request): bool => $request->data()['notification']['body'] === $body);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function retryAfterHeaders(): array
    {
        return [
            'within cap' => ['42', 42],
            'capped at 300s' => ['9999', 300],
        ];
    }

    #[DataProvider('retryAfterHeaders')]
    public function test_429_with_retry_after_releases_job(string $header, int $expectedDelay): void
    {
        Http::fake([self::V2_URL => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'slow down']], 429, ['Retry-After' => $header])]);

        $job = (new NotificationFCM(1001, 10, 'hello'))->withFakeQueueInteractions();
        $this->runJob($job);

        $job->assertReleased($expectedDelay);
        $job->assertNotFailed();
    }

    public function test_409_in_progress_releases_and_retries_with_same_key(): void
    {
        Http::fake([self::V2_URL => Http::sequence()
            ->push(['error' => ['code' => 'idempotency_in_progress', 'message' => 'in progress']], 409)
            ->push($this->okBody(['replayed' => true]))]);

        $job = (new NotificationFCM(1001, 10, 'hello'))->withFakeQueueInteractions();
        $this->runJob($job);
        $job->assertReleased(NotifyV2Service::IN_PROGRESS_RETRY_SECONDS);
        $this->assertGreaterThanOrEqual(10, NotifyV2Service::IN_PROGRESS_RETRY_SECONDS);
        $this->assertLessThanOrEqual(30, NotifyV2Service::IN_PROGRESS_RETRY_SECONDS);

        $this->runJob(unserialize(serialize(new NotificationFCM(1001, 10, 'hello'))));

        $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->data()['idempotency_key'])->all();
        $this->assertSame(['wallet-detail-1001-wu-10', 'wallet-detail-1001-wu-10'], $keys);
    }

    public function test_driver_value_is_case_and_whitespace_insensitive(): void
    {
        config(['services.notification.driver' => ' V2 ']);
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::V2_URL);
    }

    public function test_empty_url_logs_error_without_calling_notify(): void
    {
        Log::spy();
        config(['services.notify_v2.url' => '']);
        Http::fake();

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->with('NotifyV2Service url not configured', Mockery::any())->once();
    }

    public function test_missing_token_logs_error_without_calling_notify(): void
    {
        Log::spy();
        config(['services.notify_v2.token' => null]);
        Http::fake();

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->with('NotifyV2Service token not configured', Mockery::any())->once();
    }

    public function test_malformed_tokens_are_skipped_and_duplicates_removed(): void
    {
        $this->createDevice(4, 'short', now()->addDays(30));
        $this->createDevice(5, $this->tokenA, now()->addDays(30));
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSent(fn (Request $request): bool => $request->data()['tokens'] === [$this->tokenA, $this->tokenB]);
    }

    public function test_auto_calculate_uses_its_own_idempotency_namespace(): void
    {
        config(['wallet.auto_wallet.user_id' => 1]);
        DB::table('wallets')->where('id', 5)->update(['title' => now()->subMonth()->format('Y.m')]);
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        $this->artisan('command:auto_calculate_wallet')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->data()['idempotency_key'] === 'wallet-auto-calc-5-'.now()->format('Ymd').'-wu-10');
    }

    public function test_job_serialized_before_upgrade_falls_back_to_wallet_detail_key(): void
    {
        Http::fake([self::V2_URL => Http::response($this->okBody())]);

        // 部署前就排進 queue 的舊 job（舊版 class 序列化結果，沒有 idempotencyKey 欄位）
        $class = NotificationFCM::class;
        $props = [
            "\0{$class}\0walletDetailId" => 1001,
            "\0{$class}\0walletUserId" => 10,
            "\0{$class}\0message" => 'hello',
            'queue' => 'send_message',
        ];
        $serialized = sprintf('O:%d:"%s":%d:{', strlen($class), $class, count($props));
        foreach ($props as $name => $value) {
            $serialized .= serialize($name).serialize($value);
        }
        $serialized .= '}';
        $this->assertStringNotContainsString('idempotencyKey', $serialized);

        $this->runJob(unserialize($serialized));

        Http::assertSent(fn (Request $request): bool => $request->data()['idempotency_key'] === 'wallet-detail-1001-wu-10');
    }

    public function test_driver_v1_still_calls_legacy_endpoint(): void
    {
        config(['services.notification.driver' => 'v1']);
        Http::fake(['notify-v1.example.test/*' => Http::response(['ok' => true])]);

        $this->runJob(new NotificationFCM(1001, 10, 'hello'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://notify-v1.example.test/api/v1/firebase/batch'
                && $request->header('X-API-KEY') === ['v1-test-key']
                && $body['platform'] === 'FCM'
                && $body['targetId'] === '1001'
                && array_key_exists('platformParameters', $body)
                && ! array_key_exists('idempotency_key', $body)
                && count($body['users']) === 2;
        });
    }

    public function test_default_driver_is_v1_token_has_no_default_and_timeout_exceeds_notify_deadline(): void
    {
        $source = (string) file_get_contents(config_path('services.php'));
        $this->assertStringContainsString("env('NOTIFICATION_DRIVER', 'v1')", $source);
        $this->assertStringContainsString("env('NOTIFY_V2_TOKEN')", $source, 'token 不可有預設值');
        $this->assertMatchesRegularExpression('/^NOTIFY_V2_TOKEN=$/m', (string) file_get_contents(base_path('.env.example')));

        $this->assertGreaterThan(35, (int) config('services.notify_v2.timeout'));
        $job = new NotificationFCM(1, 2, 'hello');
        $this->assertTrue(! isset($job->timeout) || $job->timeout > (int) config('services.notify_v2.timeout'));
    }

    private function runJob(NotificationFCM $job): void
    {
        $job->handle(app(NotificationJobService::class));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function okBody(array $overrides = []): array
    {
        return array_merge([
            'message_id' => '01a167b4-c630-737c-99e3-2d23828c9964',
            'status' => 'succeeded',
            'replayed' => false,
            'sent' => 2,
            'failed' => 0,
            'invalid_tokens' => [],
            'results' => [],
        ], $overrides);
    }

    private function createDevice(int $id, string $fcmToken, \DateTimeInterface $expiredAt): void
    {
        DB::table('devices')->insert([
            'id' => $id,
            'user_id' => 1,
            'wallet_user_id' => 10,
            'platform' => 'web',
            'fcm_token' => $fcmToken,
            'expired_at' => $expiredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
