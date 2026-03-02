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
    public function __construct(
        private LineWebhookJobRepositoryInterface $lineWebhookJobRepository,
        private WalletService $walletService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
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

    private function isWalletCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, '/wallets') || (Str::contains($message, '帳本') && Str::contains($message, '列表'));
    }

    private function isSelectedCommand(string $message): bool
    {
        return Str::startsWith(Str::lower($message), '/selected ');
    }

    private function isAddCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, 'add ') || Str::contains($message, '新增');
    }

    private function isCalculateCommand(string $message): bool
    {
        $lower = Str::lower($message);

        return Str::startsWith($lower, '/calculate') || Str::contains($message, '結算');
    }

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

        CreateWalletDetailJob::dispatch($userId, $walletId, [
            'title' => $title,
            'amount' => $amount,
            'categoryId' => $categoryId,
            'unit' => 'TWD',
            'date' => now()->format('Y-m-d'),
        ]);

        $this->lineWebhookJobRepository->replyText($replyToken, sprintf('已新增記帳：%s %d', $title, $amount));
    }

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

    private function connectedWalletCacheKey(int $userId): string
    {
        return 'line_connected_wallet_'.$userId;
    }
}
