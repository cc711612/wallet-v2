<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Wallet\Services\WalletService;
use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Jobs\CreateWalletDetailJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LineWebhookJobService
{
    private const ADD_CONFIRM_CACHE_PREFIX = 'line_add_pending_';

    /**
     * @param  LineWebhookJobRepositoryInterface  $lineWebhookJobRepository
     * @param  WalletService  $walletService
     * @return void
     */
    public function __construct(
        private LineWebhookJobRepositoryInterface $lineWebhookJobRepository,
        private WalletService $walletService,
    ) {}

    /**
     * 接收 LINE webhook 事件並分派指令處理。
     *
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function relayWebhook(array $payload): void
    {
        $events = (array) ($payload['events'] ?? []);
        foreach ($events as $event) {
            $lineUserId = (string) data_get($event, 'source.userId', '');
            $replyToken = (string) data_get($event, 'replyToken', '');
            $messageType = (string) data_get($event, 'message.type', '');
            $message = trim((string) data_get($event, 'message.text', ''));

            if ($lineUserId === '' || $replyToken === '' || $messageType !== 'text') {
                continue;
            }

            $this->lineWebhookJobRepository->startLoading($lineUserId);

            $userId = $this->lineWebhookJobRepository->findUserIdByLineUserId($lineUserId);
            if ($userId === null) {
                $this->lineWebhookJobRepository->replyText($replyToken, '無法找到對應的使用者，請確認您的帳號是否已綁定。');

                continue;
            }

            if ($this->isWalletCommand($message)) {
                $this->handleWalletCommand($userId, $replyToken);

                continue;
            }

            if ($this->isConfirmAddCommand($message)) {
                $this->handleConfirmAddCommand($lineUserId, $userId, $replyToken);

                continue;
            }

            if ($this->isRejectAddCommand($message)) {
                $this->handleRejectAddCommand($lineUserId, $replyToken);

                continue;
            }

            if ($this->isSelectedCommand($message)) {
                $this->handleSelectedCommand($lineUserId, $userId, $message, $replyToken);

                continue;
            }

            if ($this->isAddCommand($message)) {
                $this->handleAddCommand($lineUserId, $userId, $message, $replyToken);

                continue;
            }

            if ($this->isCalculateCommand($message)) {
                $this->handleCalculateCommand($lineUserId, $userId, $replyToken);

                continue;
            }

            $this->lineWebhookJobRepository->replyText($replyToken, "可用指令:\n/wallets\n/selected <帳本代碼>\nadd <項目> <金額>\n/calculate");
        }
    }

    /**
     * 推播通知訊息到指定 LINE 使用者。
     *
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function relayNotifySendMessage(array $payload): void
    {
        $message = (string) data_get($payload, 'message', '');
        if ($message === '') {
            return;
        }

        $targetLineUserIds = array_values(array_filter(array_map('strval', (array) data_get($payload, 'userIds', []))));

        $adminLineUserIds = (array) config('bot.line.admin_user_ids', []);
        $targetLineUserIds = array_merge($targetLineUserIds, array_map('strval', $adminLineUserIds));

        $userId = (int) data_get($payload, 'user_id', 0);
        if ($userId > 0) {
            $targetLineUserIds = array_merge(
                $targetLineUserIds,
                $this->lineWebhookJobRepository->listLineUserIdsByUserIds([$userId]),
            );
        }

        $targetLineUserIds = array_values(array_unique(array_filter($targetLineUserIds, static fn (string $id): bool => $id !== '')));

        foreach ($targetLineUserIds as $lineUserId) {
            $this->lineWebhookJobRepository->pushText($lineUserId, $message);
        }
    }

    /**
     * 判斷是否為帳本列表指令。
     *
     * @param  string  $message
     * @return bool
     */
    private function isWalletCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, '/wallets') || (Str::contains($message, '帳本') && Str::contains($message, '列表'));
    }

    /**
     * 判斷是否為切換帳本指令。
     *
     * @param  string  $message
     * @return bool
     */
    private function isSelectedCommand(string $message): bool
    {
        return Str::startsWith(Str::lower($message), '/selected ');
    }

    /**
     * 判斷是否為新增記帳指令。
     *
     * @param  string  $message
     * @return bool
     */
    private function isAddCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, 'add ') || Str::contains($message, '新增');
    }

    /**
     * 判斷是否為結算指令。
     *
     * @param  string  $message
     * @return bool
     */
    private function isCalculateCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, '/calculate') || Str::contains($message, '結算');
    }

    /**
     * 判斷是否為確認新增回覆。
     *
     * @param  string  $message
     * @return bool
     */
    private function isConfirmAddCommand(string $message): bool
    {
        return Str::startsWith($message, '完全正確');
    }

    /**
     * 判斷是否為取消新增回覆。
     *
     * @param  string  $message
     * @return bool
     */
    private function isRejectAddCommand(string $message): bool
    {
        return Str::startsWith($message, '錯誤資訊');
    }

    /**
     * 回覆可用帳本列表。
     *
     * @param  int  $userId
     * @param  string  $replyToken
     * @return void
     */
    private function handleWalletCommand(int $userId, string $replyToken): void
    {
        $wallets = $this->lineWebhookJobRepository->listWalletsByUserId($userId);
        if ($wallets === []) {
            $this->lineWebhookJobRepository->replyText($replyToken, '目前沒有可用帳本');

            return;
        }

        $rows = array_map(static fn (array $wallet): string => sprintf('%s (%s)', (string) ($wallet['title'] ?? ''), (string) ($wallet['code'] ?? '')), $wallets);
        $this->lineWebhookJobRepository->replyText($replyToken, "帳本列表:\n".implode("\n", $rows)."\n\n使用 /selected <代碼> 來選擇帳本");
    }

    /**
     * 切換目前操作帳本。
     *
     * @param  string  $lineUserId
     * @param  int  $userId
     * @param  string  $message
     * @param  string  $replyToken
     * @return void
     */
    private function handleSelectedCommand(string $lineUserId, int $userId, string $message, string $replyToken): void
    {
        $code = trim((string) Str::after($message, '/selected '));
        $wallet = $this->lineWebhookJobRepository->findWalletByCodeForUser($userId, $code);
        if ($wallet === null) {
            $this->lineWebhookJobRepository->replyText($replyToken, '查無此帳本 '.$code.'，請重新選擇');

            return;
        }

        $walletId = (int) ($wallet['id'] ?? 0);
        Cache::put($this->connectedWalletCacheKey($userId), $walletId, now()->addYear());
        $this->lineWebhookJobRepository->updateSocialWalletIdByLineUserId($lineUserId, $walletId);

        $this->lineWebhookJobRepository->replyText($replyToken, '已選擇帳本: '.(string) ($wallet['title'] ?? ''));
    }

    /**
     * 解析 add 指令並建立待確認資料。
     *
     * @param  string  $lineUserId
     * @param  int  $userId
     * @param  string  $message
     * @param  string  $replyToken
     * @return void
     */
    private function handleAddCommand(string $lineUserId, int $userId, string $message, string $replyToken): void
    {
        $walletId = $this->connectedWalletId($lineUserId, $userId);
        if ($walletId === null) {
            $this->lineWebhookJobRepository->replyText($replyToken, '請先選擇帳本，輸入 /wallets 查看列表');

            return;
        }

        $raw = trim(str_replace(['add ', '新增'], '', $message));
        if ($raw === '') {
            $this->lineWebhookJobRepository->replyText($replyToken, '請輸入新增內容，例如: add 午餐 120');

            return;
        }

        preg_match('/(\d+(?:\.\d+)?)/', $raw, $matches);
        $amount = isset($matches[1]) ? (int) round((float) $matches[1]) : 0;
        $title = trim(str_replace((string) ($matches[1] ?? ''), '', $raw));
        if ($title === '') {
            $title = $raw;
        }
        if ($amount <= 0) {
            $amount = 1;
        }

        $categoryId = $this->lineWebhookJobRepository->firstCategoryId();

        $pending = [
            'title' => $title,
            'amount' => $amount,
            'categoryId' => $categoryId,
            'unit' => 'TWD',
            'date' => now()->format('Y-m-d'),
        ];

        Cache::put($this->pendingAddCacheKey($lineUserId), [
            'userId' => $userId,
            'walletId' => $walletId,
            'payload' => $pending,
        ], now()->addMinutes(5));

        $this->lineWebhookJobRepository->replyConfirmTemplate(
            $replyToken,
            '請確認記帳資料',
            sprintf("已分析記帳資料\n分類ID：%s\n金額：%d\n名稱：%s\n請確認是否正確？", (string) ($categoryId ?? '-'), $amount, $title),
            '完全正確',
            '完全正確',
            '錯誤資訊',
            '錯誤資訊'
        );
    }

    /**
     * 使用者確認後才建立帳本明細。
     *
     * @param  string  $lineUserId
     * @param  int  $userId
     * @param  string  $replyToken
     * @return void
     */
    private function handleConfirmAddCommand(string $lineUserId, int $userId, string $replyToken): void
    {
        $pending = Cache::pull($this->pendingAddCacheKey($lineUserId));
        if (! is_array($pending)) {
            $this->lineWebhookJobRepository->replyText($replyToken, '沒有待確認的記帳資料，請重新輸入 add 指令。');

            return;
        }

        $pendingUserId = (int) data_get($pending, 'userId', 0);
        $walletId = (int) data_get($pending, 'walletId', 0);
        /** @var array<string, mixed> $payload */
        $payload = (array) data_get($pending, 'payload', []);

        if ($pendingUserId !== $userId || $walletId <= 0 || $payload === []) {
            $this->lineWebhookJobRepository->replyText($replyToken, '待確認資料已失效，請重新輸入 add 指令。');

            return;
        }

        CreateWalletDetailJob::dispatch($userId, $walletId, $payload);
        $this->lineWebhookJobRepository->replyText(
            $replyToken,
            sprintf('已新增記帳：%s %d', (string) data_get($payload, 'title', ''), (int) data_get($payload, 'amount', 0))
        );
    }

    /**
     * 使用者拒絕新增時清除待確認資料。
     *
     * @param  string  $lineUserId
     * @param  string  $replyToken
     * @return void
     */
    private function handleRejectAddCommand(string $lineUserId, string $replyToken): void
    {
        Cache::forget($this->pendingAddCacheKey($lineUserId));
        $this->lineWebhookJobRepository->replyText($replyToken, '已取消本次新增，請重新輸入 add <項目> <金額>。');
    }

    /**
     * 回覆帳本結算資訊。
     *
     * @param  string  $lineUserId
     * @param  int  $userId
     * @param  string  $replyToken
     * @return void
     */
    private function handleCalculateCommand(string $lineUserId, int $userId, string $replyToken): void
    {
        $walletId = $this->connectedWalletId($lineUserId, $userId);
        if ($walletId === null) {
            $this->lineWebhookJobRepository->replyText($replyToken, '請先選擇帳本，輸入 /wallets 查看列表');

            return;
        }

        $wallet = $this->walletService->calculation($walletId);
        $income = (float) data_get($wallet, 'wallet.total.income', 0);
        $expenses = (float) data_get($wallet, 'wallet.total.expenses', 0);
        $publicIncome = (float) data_get($wallet, 'wallet.total.public.income', 0);
        $publicExpenses = (float) data_get($wallet, 'wallet.total.public.expenses', 0);

        $this->lineWebhookJobRepository->replyText(
            $replyToken,
            "帳本結算:\n收入: {$income}\n支出: {$expenses}\n公費收入: {$publicIncome}\n公費支出: {$publicExpenses}",
        );
    }

    /**
     * 取得目前連線帳本 ID。
     *
     * @param  string  $lineUserId
     * @param  int  $userId
     * @return int|null
     */
    private function connectedWalletId(string $lineUserId, int $userId): ?int
    {
        $cacheKey = $this->connectedWalletCacheKey($userId);
        $cached = Cache::get($cacheKey);
        if (is_numeric($cached)) {
            return (int) $cached;
        }

        $socialWalletId = $this->lineWebhookJobRepository->findSocialWalletIdByLineUserId($lineUserId);
        if ($socialWalletId !== null) {
            Cache::put($cacheKey, $socialWalletId, now()->addYear());
        }

        return $socialWalletId;
    }

    /**
     * 連線帳本 cache key。
     *
     * @param  int  $userId
     * @return string
     */
    private function connectedWalletCacheKey(int $userId): string
    {
        return 'line_connected_wallet_'.$userId;
    }

    /**
     * 待確認新增資料 cache key。
     *
     * @param  string  $lineUserId
     * @return string
     */
    private function pendingAddCacheKey(string $lineUserId): string
    {
        return self::ADD_CONFIRM_CACHE_PREFIX.$lineUserId;
    }
}
