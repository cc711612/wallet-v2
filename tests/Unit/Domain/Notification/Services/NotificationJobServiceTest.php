<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Notification\Services\NotificationJobService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class NotificationJobServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.notification.url' => 'https://notify.example.test',
            'services.notification.key' => 'test-key',
            'services.notification.retry_times' => 3,
            'services.notification.retry_delay_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sendFcm_returns_early_when_wallet_user_not_found(): void
    {
        $repository = new FakeNotificationJobRepository(null, []);
        $service = new NotificationJobService($repository);

        $service->sendFcm(1, 2, 'hello');

        $this->assertSame(0, $repository->sendFcmBatchCallCount);
    }

    public function test_sendFcm_returns_early_when_no_active_devices(): void
    {
        $repository = new FakeNotificationJobRepository(
            ['user_id' => 10, 'wallet_id' => 5, 'wallet' => ['code' => 'ABC']],
            []
        );
        $service = new NotificationJobService($repository);

        $service->sendFcm(1, 2, 'hello');

        $this->assertSame(0, $repository->sendFcmBatchCallCount);
    }

    public function test_sendFcm_success_does_not_log_error(): void
    {
        Log::shouldReceive('error')->never();
        Log::shouldReceive('warning')->never();

        $repository = new FakeNotificationJobRepository(
            ['user_id' => 10, 'wallet_id' => 5, 'wallet' => ['code' => 'ABC']],
            [['wallet_user_id' => 2, 'user_id' => 10, 'fcm_token' => 'token-1']]
        );
        $repository->results = [
            ['success' => true, 'status' => 200, 'body' => 'ok'],
        ];

        $service = new NotificationJobService($repository);
        $service->sendFcm(1, 2, 'hello');

        $this->assertSame(1, $repository->sendFcmBatchCallCount);
    }

    public function test_sendFcm_retries_and_logs_status_and_body_on_final_failure(): void
    {
        Log::shouldReceive('warning')->times(3);
        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'NotificationJobService sendFcm failed'
                && $context['status'] === 500
                && str_contains($context['body'], 'server error');
        });

        $repository = new FakeNotificationJobRepository(
            ['user_id' => 10, 'wallet_id' => 5, 'wallet' => ['code' => 'ABC']],
            [['wallet_user_id' => 2, 'user_id' => 10, 'fcm_token' => 'token-1']]
        );
        $repository->results = [
            ['success' => false, 'status' => 500, 'body' => 'server error 1'],
            ['success' => false, 'status' => 500, 'body' => 'server error 2'],
            ['success' => false, 'status' => 500, 'body' => 'server error 3'],
        ];

        $service = new NotificationJobService($repository);
        $service->sendFcm(1, 2, 'hello');

        $this->assertSame(3, $repository->sendFcmBatchCallCount);
    }

    public function test_sendFcm_stops_retrying_once_a_later_attempt_succeeds(): void
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->never();

        $repository = new FakeNotificationJobRepository(
            ['user_id' => 10, 'wallet_id' => 5, 'wallet' => ['code' => 'ABC']],
            [['wallet_user_id' => 2, 'user_id' => 10, 'fcm_token' => 'token-1']]
        );
        $repository->results = [
            ['success' => false, 'status' => 429, 'body' => 'rate limited'],
            ['success' => true, 'status' => 200, 'body' => 'ok'],
        ];

        $service = new NotificationJobService($repository);
        $service->sendFcm(1, 2, 'hello');

        $this->assertSame(2, $repository->sendFcmBatchCallCount);
    }

    /**
     * 使用 Http::fake 驗證真正打 HTTP 的 repository 實作，在失敗時回傳的
     * status/body 會被 Service 完整記錄下來（涵蓋 repository <-> service 的整合行為）。
     */
    public function test_real_repository_http_failure_status_and_body_are_logged(): void
    {
        Http::fake([
            'notify.example.test/*' => Http::sequence()
                ->push(['error' => 'boom'], 502)
                ->push(['error' => 'boom'], 502)
                ->push(['error' => 'boom'], 502),
        ]);

        Log::shouldReceive('warning')->times(3);
        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'NotificationJobService sendFcm failed'
                && $context['status'] === 502
                && str_contains((string) $context['body'], 'boom');
        });

        $repository = new PartialRealHttpNotificationJobRepository(
            ['user_id' => 10, 'wallet_id' => 5, 'wallet' => ['code' => 'ABC']],
            [['wallet_user_id' => 2, 'user_id' => 10, 'fcm_token' => 'token-1']]
        );

        $service = new NotificationJobService($repository);
        $service->sendFcm(1, 2, 'hello');

        Http::assertSentCount(3);
    }
}

class FakeNotificationJobRepository implements NotificationJobRepositoryInterface
{
    public int $sendFcmBatchCallCount = 0;

    /** @var array<int, array{success: bool, status: int, body: string}> */
    public array $results = [];

    /**
     * @param  array<string, mixed>|null  $walletUser
     * @param  array<int, array<string, mixed>>  $devices
     */
    public function __construct(private ?array $walletUser, private array $devices) {}

    public function findWalletUser(int $walletUserId): ?array
    {
        return $this->walletUser;
    }

    public function findWalletUserWithWallet(int $walletUserId): ?array
    {
        return $this->walletUser;
    }

    public function listActiveDevicesByOwner(?int $userId, int $walletUserId): array
    {
        return $this->devices;
    }

    public function sendFcmBatch(array $requestBody): array
    {
        $index = $this->sendFcmBatchCallCount;
        $this->sendFcmBatchCallCount++;

        return $this->results[$index] ?? ['success' => false, 'status' => 0, 'body' => ''];
    }
}

class PartialRealHttpNotificationJobRepository implements NotificationJobRepositoryInterface
{
    /**
     * @param  array<string, mixed>|null  $walletUser
     * @param  array<int, array<string, mixed>>  $devices
     */
    public function __construct(private ?array $walletUser, private array $devices) {}

    public function findWalletUser(int $walletUserId): ?array
    {
        return $this->walletUser;
    }

    public function findWalletUserWithWallet(int $walletUserId): ?array
    {
        return $this->walletUser;
    }

    public function listActiveDevicesByOwner(?int $userId, int $walletUserId): array
    {
        return $this->devices;
    }

    public function sendFcmBatch(array $requestBody): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => 'test-key',
                ])
                ->post('https://notify.example.test/api/v1/firebase/batch', $requestBody);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (RequestException $exception) {
            return ['success' => false, 'status' => 0, 'body' => $exception->getMessage()];
        }
    }
}
