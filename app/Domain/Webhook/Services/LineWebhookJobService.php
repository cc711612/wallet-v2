<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Domain\Gemini\Services\GeminiService;
use App\Jobs\CreateWalletDetailJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
        private GeminiService $geminiService,
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

        return Str::startsWith($lower, '/wallets') || Str::contains($message, ['帳本', '列表']);
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

        $this->lineWebhookJobRepository->replyWalletSelectionTemplate($replyToken, $wallets);
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

        $categories = $this->lineWebhookJobRepository->listCategories();
        if ($categories === []) {
            $this->lineWebhookJobRepository->replyText($replyToken, '目前尚未設定分類，請先建立分類。');

            return;
        }

        try {
            $normalized = $this->normalizeAddCommandByAi($raw, $categories);
        } catch (RuntimeException $exception) {
            $this->lineWebhookJobRepository->replyText($replyToken, $exception->getMessage());

            return;
        }

        $categoryId = (int) data_get($normalized, 'categoryId', 0);
        $amount = (int) data_get($normalized, 'amount', 0);
        $title = (string) data_get($normalized, 'title', '');
        $categoryName = (string) data_get($normalized, 'categoryName', '未知分類');

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
            sprintf("已分析記帳資料\n分類：%s\n金額：%d\n名稱：%s\n請確認是否正確？", $categoryName, $amount, $title),
            '完全正確',
            '完全正確',
            '錯誤資訊',
            '錯誤資訊'
        );
    }

    /**
     * 使用 AI 將使用者輸入正規化為可入庫資料。
     *
     * @param  string  $raw
     * @param  array<int, array{id:int,name:string}>  $categories
     * @return array{categoryId:int,categoryName:string,amount:int,title:string}
     */
    private function normalizeAddCommandByAi(string $raw, array $categories): array
    {
        $categoryList = collect($categories)
            ->map(static fn (array $category): string => sprintf('id=%d,name=%s', $category['id'], $category['name']))
            ->implode('; ');

        $messages = [[
            'role' => 'user',
            'content' => implode("\n", [
                '你是一個記帳分析助手，請嚴格依照格式回傳，不要有任何多餘說明。',
                '現在時間：'.now()->format('Y-m-d H:i:s'),
                '可用分類（請從中選擇最接近的一項）：'.$categoryList,
                '請分析以下內容，回傳純 JSON（不要 markdown code block）：',
                '{"categoryId":<數字>,"amount":<整數>,"title":"<名稱>"}',
                '使用者輸入：'.$raw,
            ]),
        ]];

        try {
            $response = $this->geminiService->chat($messages);
            $text = $this->extractGeminiText($response);
        } catch (Throwable) {
            throw new RuntimeException('AI 服務暫時無法使用，請稍後再試');
        }

        if ($text === '') {
            throw new RuntimeException('AI 無回應，請稍後再試');
        }

        $normalized = trim((string) preg_replace('/```(?:json)?|```/i', '', $text));
        $parsed = json_decode($normalized, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('無法解析 AI 回應，請嘗試更明確的描述，例如「午餐 120」。');
        }

        $categoryId = (int) data_get($parsed, 'categoryId', 0);
        $amount = (int) round((float) data_get($parsed, 'amount', 0));
        $title = trim((string) data_get($parsed, 'title', ''));

        if ($categoryId <= 0 || $amount <= 0 || $title === '') {
            throw new RuntimeException('解析結果不完整，請重新輸入。');
        }

        $matchedCategory = collect($categories)
            ->first(static fn (array $category): bool => $category['id'] === $categoryId);
        $categoryName = is_array($matchedCategory) ? (string) ($matchedCategory['name'] ?? '未知分類') : '未知分類';

        return [
            'categoryId' => $categoryId,
            'categoryName' => (string) $categoryName,
            'amount' => $amount,
            'title' => $title,
        ];
    }

    /**
     * 從 Gemini 回應取出主要文字內容。
     *
     * @param  array<string, mixed>  $response
     * @return string
     */
    private function extractGeminiText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part)) {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return trim($text);
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

        $summary = $this->lineWebhookJobRepository->getWalletCalculateSummary($walletId);
        if ($summary === null) {
            $this->lineWebhookJobRepository->replyText($replyToken, '查無此帳本，請重新選擇');

            return;
        }

        $messages = [];
        $messages[] = '帳本名稱: '.(string) data_get($summary, 'title', '');
        $messages[] = '公費總支出金額: '.(float) data_get($summary, 'public_expense_total', 0);

        /** @var array<int, array<string, mixed>> $members */
        $members = (array) data_get($summary, 'members', []);
        foreach ($members as $member) {
            $messages[] = '帳本成員: '.(string) data_get($member, 'name', '');
            $messages[] = '帳本成員總代墊金額: '.(float) data_get($member, 'payment_total', 0);
        }

        $messages[] = '總支出金額: '.(float) data_get($summary, 'total', 0);

        /** @var array<string, mixed> $analysis */
        $analysis = (array) data_get($summary, 'analysis', []);
        if ($analysis !== []) {
            $messages[] = '--- 帳本明細分析 ---';
            $messages[] = '支出筆數: '.(int) data_get($analysis, 'expense_count', 0);
            $messages[] = '平均每筆支出: '.(float) data_get($analysis, 'average_expense', 0);
            $messages[] = '近 30 天支出: '.(float) data_get($analysis, 'recent_30_days_total', 0);

            $topCategoryName = (string) data_get($analysis, 'top_category_name', '');
            if ($topCategoryName !== '') {
                $messages[] = '最高支出分類: '.$topCategoryName.' / '.(float) data_get($analysis, 'top_category_total', 0);
            }

            $topPayerName = (string) data_get($analysis, 'top_payer_name', '');
            if ($topPayerName !== '') {
                $messages[] = '最高代墊成員: '.$topPayerName.' / '.(float) data_get($analysis, 'top_payer_total', 0);
            }

            $maxExpense = (array) data_get($analysis, 'max_expense', []);
            if ($maxExpense !== []) {
                $messages[] = '最大單筆: '.(string) data_get($maxExpense, 'title', '')
                    .' / '.(float) data_get($maxExpense, 'value', 0)
                    .' / '.(string) data_get($maxExpense, 'date', '');
            }
        }

        $messages[] = '結算時間: '.now()->format('Y-m-d H:i:s');

        $this->lineWebhookJobRepository->replyText($replyToken, implode("\r\n", $messages));
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
