<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * AI 備援路徑的把關（實驗性；誤殺可接受，錯記不可接受）。以「正向條件」為主、排除詞為輔：
 *
 * 呼叫 AI 前：
 * - 整段原文命中排除詞（強／軟／AI 額外清單）或任何外幣線索 → 拒絕。
 * - 遮罩後原文中必須有「台幣金額候選」：數字前有 NT$／NTD／TWD／新台幣／$，或後接「元／塊」；
 *   不能是日期、時間或累計／額度／餘額等語境的數字；且所在子句含消費語境詞。沒有 → 拒絕。
 *
 * AI 回傳後：
 * - 台幣金額候選必須只有一個值，且等於 AI 的金額（round 到 2 位）。
 * - AI 的商店必須是遮罩後原文的子字串（大小寫、全半形不計），出現在金額之前或緊接在「商店：」這類標籤後，
 *   且不得含金額標記或消費語境詞。
 */
class AiExpenseGuard
{
    /** AI 路徑額外的排除詞（整段原文掃描；輔助防線） */
    private const EXTRA_EXCLUDE_PATTERN = '/爭議|掛失|攔截|暫緩|退還|預約|調整|尚未付款|待付款|解除圈存|圈存|發票|帳單|請款|轉帳|轉出|匯款|退費|退回|調升|轉入|保費|理賠|未能完成|駁回|返還|入戶|撥入|存入|註銷|撤回|刷退|應付|儲值|餘額|中獎|紅包|薪資|未獲|匯入|待確認|發放|股息|配息/u';

    /** 消費語境詞：金額所在子句必須出現其一 */
    private const CONSUMPTION_PATTERN = '/消費|刷卡|授權|交易|付款|支付|簽帳|扣款/u';

    /** 數字 token（不含日期、時間片段） */
    private const NUMBER_PATTERN = '/(?<![\d.,\/:\-])(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?![\d\/:]|,\d|\.\d)/u';

    private const TWD_PREFIX = '/(?:NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$)\s*$/u';

    private const TWD_SUFFIX = '/^\s*(?:元|塊)/u';

    private const AMOUNT_CONTEXT = '/累計|額度|餘額|可用|應繳|應付|帳單|最低|上限|合計|總計/u';

    private const CLAUSE_DELIMITER = '/[。！？!?；;\n，、]|(?<!\d),|,(?!\d)/u';

    private const MERCHANT_LABEL = '/(?:商店|特店|商家|店名|特約商店)(?:名稱)?\s*[:：]\s*$/u';

    private const MERCHANT_FORBIDDEN = '/NT\$|NTD|TWD|新台幣|新臺幣|元|消費|刷卡|授權|交易|付款|支付|簽帳|扣款/u';

    private ExpenseTextScreen $screen;

    public function __construct(private RuleBasedNotificationParser $ruleParser, ?ExpenseTextScreen $screen = null)
    {
        $this->screen = $screen ?? new ExpenseTextScreen($ruleParser);
    }

    /**
     * 排除詞與外幣線索（整段原文，不縮小範圍）。
     *
     * @param  string  $text  已清理並全形轉半形的原文
     */
    public function rejectsBeforeAi(string $text): bool
    {
        if ($this->ruleParser->wholeTextRejectionReason($text) !== null) {
            return true;
        }

        if (preg_match(self::EXTRA_EXCLUDE_PATTERN, $text) !== 0) {
            return true;
        }

        return $this->screen->hasForeignClue($text);
    }

    /**
     * 正向條件：遮罩後原文至少有一個「台幣金額候選」。
     */
    public function hasTwdExpenseAmount(string $text): bool
    {
        return $this->twdAmountCandidates(NotificationTextMasker::mask($text)) !== [];
    }

    /**
     * AI 結果的正向檢查：金額唯一且相符、商店是原文子字串且位置合理。
     */
    public function acceptsAiResult(ParsedExpense $parsed, string $text): bool
    {
        $masked = NotificationTextMasker::mask($text);
        $candidates = $this->twdAmountCandidates($masked);

        $values = array_values(array_unique(array_map(static fn (array $c): string => number_format($c['value'], 2, '.', ''), $candidates)));
        if (count($values) !== 1 || $values[0] !== number_format(round($parsed->amount, 2), 2, '.', '')) {
            return false;
        }

        return $this->merchantIsPlausible($parsed->merchant, $masked, $candidates[0]['offset']);
    }

    /**
     * AI 回傳的金額（已 round 到 2 位）必須等於原文中的某個數字（去千分位，容許 .00）。
     */
    public function amountAppearsInText(float $amount, string $text): bool
    {
        $plain = (string) preg_replace('/(?<=\d),(?=\d{3}(?!\d))/u', '', $text);
        if (preg_match_all('/\d+(?:\.\d+)?/u', $plain, $matches) === false) {
            return false;
        }

        foreach ($matches[0] as $number) {
            if (abs(round((float) $number, 2) - round($amount, 2)) < 0.00001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{value: float, offset: int}>
     */
    private function twdAmountCandidates(string $masked): array
    {
        if (preg_match_all(self::NUMBER_PATTERN, $masked, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $candidates = [];
        foreach ($matches[0] as [$token, $offset]) {
            $prefix = mb_strcut($masked, max(0, $offset - 40), min(40, $offset));
            $suffix = mb_strcut($masked, $offset + strlen($token), 12);

            if (preg_match(self::TWD_PREFIX, $prefix) !== 1 && preg_match(self::TWD_SUFFIX, $suffix) !== 1) {
                continue;
            }

            // 累計、額度、餘額…語境的金額不是本筆消費
            if (preg_match(self::AMOUNT_CONTEXT, mb_substr($prefix, -10)) === 1) {
                continue;
            }

            if (preg_match(self::CONSUMPTION_PATTERN, $this->clauseAt($masked, $offset, strlen($token))) !== 1) {
                continue;
            }

            $value = round((float) str_replace(',', '', $token), 2);
            if ($value > 0 && $value <= RuleBasedNotificationParser::MAX_AMOUNT) {
                $candidates[] = ['value' => $value, 'offset' => $offset];
            }
        }

        return $candidates;
    }

    private function clauseAt(string $text, int $offset, int $length): string
    {
        $before = substr($text, 0, $offset);
        $after = substr($text, $offset + $length);

        $start = preg_split(self::CLAUSE_DELIMITER, $before);
        $end = preg_split(self::CLAUSE_DELIMITER, $after);

        return (is_array($start) ? (string) end($start) : $before)
            .substr($text, $offset, $length)
            .(is_array($end) ? (string) ($end[0] ?? '') : $after);
    }

    private function merchantIsPlausible(string $merchant, string $masked, int $amountOffset): bool
    {
        // 換行與 tab 逐字換成空白（不改變位元組位移），大小寫不計；全半形已在前面統一
        $needle = mb_strtolower(trim(strtr(NotificationTextMasker::sanitize(NotificationTextMasker::normalizeWidth($merchant)), "\r\n\t", '   ')));
        if ($needle === '' || preg_match(self::MERCHANT_FORBIDDEN, $needle) === 1) {
            return false;
        }

        $haystack = mb_strtolower(strtr($masked, "\r\n\t", '   '));
        $position = strpos($haystack, $needle);
        if ($position === false) {
            return false;
        }

        if ($position < $amountOffset) {
            return true;
        }

        return preg_match(self::MERCHANT_LABEL, substr($haystack, 0, $position)) === 1;
    }
}
