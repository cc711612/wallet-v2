<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Domain\Wallet\Ingest\AiNotificationParser;
use App\Domain\Wallet\Ingest\NotificationTextMasker;
use App\Domain\Wallet\Ingest\ParsedExpense;
use App\Domain\Wallet\Ingest\RuleBasedNotificationParser;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 銀行刷卡推播自動記帳：解析推播原文（規則優先、AI 備援）後建立一筆個人消費，並以 Cache 去重。
 */
class WalletDetailIngestService
{
    public const UNPARSEABLE_MESSAGE = '無法解析推播內容';

    public const NOTE_PREFIX = '[auto-ingest]';

    private const NOTE_TEXT_LIMIT = 1000;

    private const TITLE_LIMIT = 255;

    private const DEDUP_TTL_DAYS = 30;

    private const LOCK_SECONDS = 30;

    public function __construct(
        private WalletDetailService $walletDetailService,
        private WalletDetailQueryRepositoryInterface $walletDetailQueryRepository,
        private RuleBasedNotificationParser $ruleParser,
        private AiNotificationParser $aiParser,
    ) {}

    /**
     * @return array{id:int, title:string|null, value:float|null, date:string|null, category_id:int|null, parser:string|null, duplicate:bool}
     *
     * @throws WalletDetailBusinessException 無法解析（不寫入）
     * @throws RuntimeException AI 服務失敗、同一則推播正在處理中
     */
    public function ingest(int $walletId, int $walletUserId, string $text, string $receivedAt): array
    {
        $received = $this->receivedAt($receivedAt);
        $dedupKey = $this->dedupKey($walletId, $walletUserId, $text, $received);

        // 已處理過：不再解析（也不呼叫 AI）
        $existingId = $this->existingDetailId($dedupKey);
        if ($existingId !== null) {
            return $this->duplicateResult($walletId, $existingId);
        }

        // 解析沒有副作用，失敗直接丟例外，不會留下去重 key
        $parsed = $this->parse($text, $received);

        try {
            return Cache::lock($dedupKey.':lock', self::LOCK_SECONDS)->block(
                $this->lockWaitSeconds(),
                function () use ($walletId, $walletUserId, $text, $received, $dedupKey, $parsed): array {
                    // 拿到鎖後再檢查一次：併發的另一個請求可能已建立完成
                    $existingId = $this->existingDetailId($dedupKey);
                    if ($existingId !== null) {
                        return $this->duplicateResult($walletId, $existingId);
                    }

                    $detailId = $this->createDetail($walletId, $walletUserId, $text, $received, $parsed);
                    Cache::put($dedupKey, $detailId, now()->addDays(self::DEDUP_TTL_DAYS));

                    Log::info('WalletDetailIngestService created detail', [
                        'wallet_id' => $walletId,
                        'wallet_user_id' => $walletUserId,
                        'detail_id' => $detailId,
                        'parser' => $parsed->parser,
                    ]);

                    return [
                        'id' => $detailId,
                        'title' => $this->title($parsed),
                        'value' => $parsed->amount,
                        'date' => $this->detailDate($parsed, $received),
                        'category_id' => null,
                        'parser' => $parsed->parser,
                        'duplicate' => false,
                    ];
                }
            );
        } catch (LockTimeoutException) {
            throw new RuntimeException('相同推播正在處理中，請稍後再試');
        }
    }

    /**
     * 去重 key：帳本 + 建立者 + 原文（trim）+ 接收時間（台北時間，取到分鐘）。
     */
    public function dedupKey(int $walletId, int $walletUserId, string $text, CarbonImmutable $receivedAt): string
    {
        $minute = $receivedAt->setTimezone($this->timezone())->format('Y-m-d H:i');

        return 'wallet_detail_ingest:'.hash('sha256', implode("\n", [$walletId, $walletUserId, trim($text), $minute]));
    }

    public function receivedAt(string $receivedAt): CarbonImmutable
    {
        return CarbonImmutable::parse($receivedAt)->setTimezone($this->timezone());
    }

    private function parse(string $text, CarbonImmutable $received): ParsedExpense
    {
        // 非法 UTF-8 無法可靠解析或遮罩，直接拒絕（也不送 AI）
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new WalletDetailBusinessException(self::UNPARSEABLE_MESSAGE);
        }

        // 先清理控制／格式字元，再全形轉半形（解析、遮罩、送 AI 都用這份）
        $text = trim(NotificationTextMasker::normalizeWidth(NotificationTextMasker::sanitize($text)));
        if ($text === '') {
            throw new WalletDetailBusinessException(self::UNPARSEABLE_MESSAGE);
        }

        $parsed = $this->ruleParser->parse($text, $received);
        if ($parsed !== null) {
            return $parsed;
        }

        if (! (bool) config('services.ingest.ai_enabled', false)) {
            throw new WalletDetailBusinessException(self::UNPARSEABLE_MESSAGE);
        }

        // 原文已命中強排除或外幣標記：不浪費 AI 呼叫
        if ($this->ruleParser->rejectionReason($text, null, false) !== null) {
            throw new WalletDetailBusinessException(self::UNPARSEABLE_MESSAGE);
        }

        $parsed = $this->aiParser->parse($text, $received);

        // AI 結果交叉檢查：原文命中外幣或排除規則一律拒絕，即使 AI 回 TWD／is_expense=true
        if ($this->ruleParser->rejectionReason($text, $parsed->merchant) !== null) {
            throw new WalletDetailBusinessException(self::UNPARSEABLE_MESSAGE);
        }

        return $parsed;
    }

    private function createDetail(int $walletId, int $walletUserId, string $text, CarbonImmutable $received, ParsedExpense $parsed): int
    {
        // 欄位固定為「建立者自己的個人消費」，不接受 client 指定，因此不會觸發推播給其他成員
        $walletDetail = WalletDetail::fromPayload([
            'wallet' => $walletId,
            'wallet_user_id' => $walletUserId,
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => $this->title($parsed),
            'value' => $parsed->amount,
            'select_all' => false,
            'is_personal' => true,
            'payment_wallet_user_id' => $walletUserId,
            'users' => [$walletUserId],
            'splits' => [],
            'unit' => 'TWD',
            'date' => $this->detailDate($parsed, $received),
            'note' => self::NOTE_PREFIX.' '.mb_substr(NotificationTextMasker::mask(NotificationTextMasker::singleLine($text)), 0, self::NOTE_TEXT_LIMIT),
            'category_id' => null,
        ]);

        $created = $this->walletDetailService->create($walletDetail);
        $detailId = (int) ($created['id'] ?? 0);
        if ($detailId <= 0) {
            throw new RuntimeException('系統錯誤，請稍後再試');
        }

        return $detailId;
    }

    /**
     * 商店名寫入前同樣遮罩卡號，並限制長度。
     */
    private function title(ParsedExpense $parsed): string
    {
        return mb_substr(NotificationTextMasker::mask(NotificationTextMasker::singleLine($parsed->merchant)), 0, self::TITLE_LIMIT);
    }

    private function detailDate(ParsedExpense $parsed, CarbonImmutable $received): string
    {
        return $parsed->date ?? $received->toDateString();
    }

    private function existingDetailId(string $dedupKey): ?int
    {
        $value = Cache::get($dedupKey);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return array{id:int, title:string|null, value:float|null, date:string|null, category_id:int|null, parser:null, duplicate:true}
     */
    private function duplicateResult(int $walletId, int $detailId): array
    {
        // 明細可能已被使用者刪除，此時只回 id
        $summary = $this->walletDetailQueryRepository->findDetailSummary($walletId, $detailId);

        return [
            'id' => $detailId,
            'title' => $summary['title'] ?? null,
            'value' => $summary['value'] ?? null,
            'date' => $summary['date'] ?? null,
            'category_id' => $summary['category_id'] ?? null,
            'parser' => null,
            'duplicate' => true,
        ];
    }

    private function lockWaitSeconds(): int
    {
        return max(0, (int) config('services.ingest.lock_wait_seconds', 10));
    }

    private function timezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Taipei');
    }
}
