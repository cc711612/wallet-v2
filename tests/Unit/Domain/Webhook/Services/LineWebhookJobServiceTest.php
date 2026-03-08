<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook\Services;

use App\Domain\Gemini\Services\GeminiService;
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

        $geminiService = Mockery::mock(GeminiService::class);
        $service = new LineWebhookJobService($repo, $geminiService);

        $service->relayWebhook($this->eventPayload('/wallets', 'U1', 'r1'));

        $this->assertCount(1, $repo->walletSelectionReplies);
        $this->assertSame('r1', $repo->walletSelectionReplies[0]['replyToken']);
        $this->assertSame('ABC123', $repo->walletSelectionReplies[0]['wallets'][0]['code']);
    }

    public function test_selected_command_updates_connected_wallet(): void
    {
        Cache::flush();
        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U2'] = 2;
        $repo->walletByCodeAndUser['2:ABC123'] = ['id' => 63, 'title' => '2026.03', 'code' => 'ABC123'];

        $geminiService = Mockery::mock(GeminiService::class);
        $service = new LineWebhookJobService($repo, $geminiService);

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
        $repo->categories = [
            ['id' => 9, 'name' => '餐飲'],
        ];

        $geminiService = Mockery::mock(GeminiService::class);
        $geminiService
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => '{"categoryId":9,"amount":120,"title":"午餐"}']],
                    ],
                ]],
            ]);

        $service = new LineWebhookJobService($repo, $geminiService);

        $service->relayWebhook($this->eventPayload('add 午餐 120', 'U3', 'r3'));

        Bus::assertNotDispatched(CreateWalletDetailJob::class);
        $this->assertCount(1, $repo->confirmTemplateReplies);
        $this->assertTrue(Cache::has('line_add_pending_U3'));
    }

    public function test_confirm_add_command_dispatches_wallet_detail_job(): void
    {
        Bus::fake();
        Cache::flush();

        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U5'] = 5;

        Cache::put('line_add_pending_U5', [
            'userId' => 5,
            'walletId' => 63,
            'payload' => [
                'title' => '午餐',
                'amount' => 120,
                'categoryId' => 9,
                'unit' => 'TWD',
                'date' => '2026-03-08',
            ],
        ], now()->addMinutes(5));

        $geminiService = Mockery::mock(GeminiService::class);
        $service = new LineWebhookJobService($repo, $geminiService);

        $service->relayWebhook($this->eventPayload('完全正確 :午餐', 'U5', 'r5'));

        Bus::assertDispatched(CreateWalletDetailJob::class);
        $this->assertCount(1, $repo->replies);
        $this->assertStringContainsString('已新增記帳', $repo->replies[0]['message']);
        $this->assertFalse(Cache::has('line_add_pending_U5'));
    }

    public function test_reject_add_command_clears_pending_cache_without_dispatch(): void
    {
        Bus::fake();
        Cache::flush();

        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U6'] = 6;

        Cache::put('line_add_pending_U6', [
            'userId' => 6,
            'walletId' => 63,
            'payload' => ['title' => '晚餐', 'amount' => 150],
        ], now()->addMinutes(5));

        $geminiService = Mockery::mock(GeminiService::class);
        $service = new LineWebhookJobService($repo, $geminiService);

        $service->relayWebhook($this->eventPayload('錯誤資訊 :晚餐', 'U6', 'r6'));

        Bus::assertNotDispatched(CreateWalletDetailJob::class);
        $this->assertCount(1, $repo->replies);
        $this->assertStringContainsString('已取消本次新增', $repo->replies[0]['message']);
        $this->assertFalse(Cache::has('line_add_pending_U6'));
    }

    public function test_calculate_command_replies_wallet_totals(): void
    {
        Cache::flush();
        Cache::put('line_connected_wallet_4', 63);

        $repo = new FakeLineWebhookJobRepository;
        $repo->lineUserToUserId['U4'] = 4;
        $repo->walletCalculateSummaryByWalletId[63] = [
            'title' => '2026.03',
            'public_expense_total' => 300,
            'members' => [
                ['name' => 'Roy', 'payment_total' => 120],
            ],
            'total' => 700,
            'analysis' => [
                'expense_count' => 8,
                'average_expense' => 87.5,
                'recent_30_days_total' => 680,
                'top_payer_name' => 'Roy',
                'top_payer_total' => 320,
                'top_category_name' => '餐飲',
                'top_category_total' => 420,
                'max_expense' => [
                    'title' => '聚餐',
                    'value' => 280,
                    'date' => '2026-03-07',
                ],
            ],
        ];

        $geminiService = Mockery::mock(GeminiService::class);
        $service = new LineWebhookJobService($repo, $geminiService);
        $service->relayWebhook($this->eventPayload('/calculate', 'U4', 'r4'));

        $this->assertCount(1, $repo->replies);
        $this->assertStringContainsString('帳本名稱: 2026.03', $repo->replies[0]['message']);
        $this->assertStringContainsString('公費總支出金額: 300', $repo->replies[0]['message']);
        $this->assertStringContainsString('總支出金額: 700', $repo->replies[0]['message']);
        $this->assertStringContainsString('支出筆數: 8', $repo->replies[0]['message']);
        $this->assertStringContainsString('最高支出分類: 餐飲 / 420', $repo->replies[0]['message']);
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

    /** @var array<int, array{id:int,name:string}> */
    public array $categories = [];

    /** @var array<int, array<string, mixed>> */
    public array $walletCalculateSummaryByWalletId = [];

    /** @var array<int, array<string, string>> */
    public array $replies = [];

    /** @var array<int, array<string, string>> */
    public array $pushes = [];

    /** @var array<int, array<string, mixed>> */
    public array $confirmTemplateReplies = [];

    /** @var array<int, array<string, mixed>> */
    public array $walletSelectionReplies = [];

    public function startLoading(string $lineUserId): void {}

    public function replyText(string $replyToken, string $message): void
    {
        $this->replies[] = ['replyToken' => $replyToken, 'message' => $message];
    }

    public function pushText(string $lineUserId, string $message): void
    {
        $this->pushes[] = ['lineUserId' => $lineUserId, 'message' => $message];
    }

    public function replyConfirmTemplate(
        string $replyToken,
        string $altText,
        string $promptText,
        string $confirmLabel,
        string $confirmText,
        string $rejectLabel,
        string $rejectText
    ): void {
        $this->confirmTemplateReplies[] = [
            'replyToken' => $replyToken,
            'altText' => $altText,
            'promptText' => $promptText,
            'confirmLabel' => $confirmLabel,
            'confirmText' => $confirmText,
            'rejectLabel' => $rejectLabel,
            'rejectText' => $rejectText,
        ];
    }

    public function replyWalletSelectionTemplate(string $replyToken, array $wallets): void
    {
        $this->walletSelectionReplies[] = [
            'replyToken' => $replyToken,
            'wallets' => $wallets,
        ];
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
        return $this->categories[0]['id'] ?? null;
    }

    public function listCategories(): array
    {
        return $this->categories;
    }

    public function getWalletCalculateSummary(int $walletId): ?array
    {
        return $this->walletCalculateSummaryByWalletId[$walletId] ?? null;
    }
}
