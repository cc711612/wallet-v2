<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook\Services;

use App\Domain\Wallet\Services\WalletService;
use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Domain\Webhook\Services\LineWebhookJobService;
use App\Jobs\CreateWalletDetailJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LineWebhookJobServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_wallets_command_replies_wallet_list(): void
    {
        Cache::flush();
        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U1'] = 1;
        $repo->walletsByUserId[1] = [
            ['id' => 63, 'title' => '2026.03', 'code' => 'ABC123'],
            ['id' => 64, 'title' => 'Trip', 'code' => 'TRIP01'],
        ];

        $walletService = Mockery::mock(WalletService::class);
        $service = new LineWebhookJobService($repo, $walletService);

        $service->relayWebhook($this->eventPayload('/wallets', 'U1', 'r1'));

        $this->assertCount(1, $repo->replies);
        $this->assertStringContainsString('帳本列表', $repo->replies[0]['message']);
        $this->assertStringContainsString('ABC123', $repo->replies[0]['message']);
    }

    public function test_selected_command_updates_connected_wallet(): void
    {
        Cache::flush();
        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U2'] = 2;
        $repo->walletByCodeAndUser['2:ABC123'] = ['id' => 63, 'title' => '2026.03', 'code' => 'ABC123'];

        $walletService = Mockery::mock(WalletService::class);
        $service = new LineWebhookJobService($repo, $walletService);

        $service->relayWebhook($this->eventPayload('/selected ABC123', 'U2', 'r2'));

        $this->assertSame(63, $repo->updatedSocialWalletId['U2'] ?? null);
        $this->assertSame(63, Cache::get('line_connected_wallet_2'));
        $this->assertStringContainsString('已選擇帳本', $repo->replies[0]['message']);
    }

    public function test_add_command_dispatches_create_wallet_detail_job(): void
    {
        Bus::fake();
        Cache::flush();

        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U3'] = 3;
        $repo->socialWalletIdByLineUser['U3'] = 63;
        $repo->firstCategoryId = 9;

        $walletService = Mockery::mock(WalletService::class);
        $service = new LineWebhookJobService($repo, $walletService);

        $service->relayWebhook($this->eventPayload('add 午餐 120', 'U3', 'r3'));

        Bus::assertDispatched(CreateWalletDetailJob::class);
        $this->assertStringContainsString('已新增記帳', $repo->replies[0]['message']);
    }

    public function test_calculate_command_replies_wallet_totals(): void
    {
        Cache::flush();
        Cache::put('line_connected_wallet_4', 63);

        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U4'] = 4;

        $walletService = Mockery::mock(WalletService::class);
        $walletService
            ->shouldReceive('calculation')
            ->once()
            ->with(63)
            ->andReturn([
                'wallet' => [
                    'total' => [
                        'income' => 1000,
                        'expenses' => 700,
                        'public' => ['income' => 500, 'expenses' => 300],
                    ],
                ],
            ]);

        $service = new LineWebhookJobService($repo, $walletService);
        $service->relayWebhook($this->eventPayload('/calculate', 'U4', 'r4'));

        $this->assertCount(1, $repo->replies);
        $this->assertStringContainsString('帳本結算', $repo->replies[0]['message']);
        $this->assertStringContainsString('收入: 1000', $repo->replies[0]['message']);
        $this->assertStringContainsString('支出: 700', $repo->replies[0]['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(string $message, string $lineUserId, string $replyToken): array
    {
        return [
            'events' => [[
                'replyToken' => $replyToken,
                'source' => ['userId' => $lineUserId],
                'message' => ['type' => 'text', 'text' => $message],
            ]],
        ];
    }
}

class FakeLineWebhookJobRepository implements LineWebhookJobRepositoryInterface
{
    /** @var array<string, int> */
    public array $lineUserToUserId = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $walletsByUserId = [];

    /** @var array<string, array<string, mixed>> */
    public array $walletByCodeAndUser = [];

    /** @var array<string, int> */
    public array $updatedSocialWalletId = [];

    /** @var array<string, int> */
    public array $socialWalletIdByLineUser = [];

    public ?int $firstCategoryId = 1;

    /** @var array<int, array<string, string>> */
    public array $replies = [];

    /** @var array<int, array<string, string>> */
    public array $pushes = [];

    public function startLoading(string $lineUserId): void {}

    public function replyText(string $replyToken, string $message): void
    {
        $this->replies[] = ['replyToken' => $replyToken, 'message' => $message];
    }

    public function pushText(string $lineUserId, string $message): void
    {
        $this->pushes[] = ['lineUserId' => $lineUserId, 'message' => $message];
    }

    public function findUserIdByLineUserId(string $lineUserId): ?int
    {
        return $this->lineUserToUserId[$lineUserId] ?? null;
    }

    public function listWalletsByUserId(int $userId): array
    {
        return $this->walletsByUserId[$userId] ?? [];
    }

    public function findWalletByCodeForUser(int $userId, string $code): ?array
    {
        return $this->walletByCodeAndUser[$userId.':'.$code] ?? null;
    }

    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void
    {
        $this->updatedSocialWalletId[$lineUserId] = $walletId;
        $this->socialWalletIdByLineUser[$lineUserId] = $walletId;
    }

    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int
    {
        return $this->socialWalletIdByLineUser[$lineUserId] ?? null;
    }

    public function listLineUserIdsByUserIds(array $userIds): array
    {
        return [];
    }

    public function firstCategoryId(): ?int
    {
        return $this->firstCategoryId;
    }
}
