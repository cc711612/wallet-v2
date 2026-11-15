<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * AI 備援路徑的保守把關（誤殺可接受，錯記不可接受）：
 * - 呼叫 AI 前：整段原文命中排除字或任何外幣線索 → 拒絕。
 * - AI 回傳後：AI 給的金額必須真的出現在原文的數字中。
 */
class AiExpenseGuard
{
    /** AI 路徑額外的強排除（整段原文掃描） */
    private const EXTRA_EXCLUDE_PATTERN = '/爭議|掛失|攔截|暫緩|退還|預約|調整|尚未付款|待付款|解除圈存|圈存|發票|帳單|請款|轉帳|轉出|匯款|退費/u';

    /** AI 路徑額外的外幣線索：CA$、A$、NZ$、US $（符號與 $ 間可有空白） */
    private const EXTRA_FOREIGN_PATTERN = '/(?<![A-Za-z])(?:CA|US|NZ|AU|HK|CN|A|C|S)\s*\$/iu';

    /** 原文任何位置出現美國／海外／國外，且金額是裸 $（不是 NT$） */
    private const OVERSEAS_PATTERN = '/美國|海外|國外/u';

    private const BARE_DOLLAR_PATTERN = '/(?<![A-Za-z])\$\s*\d/u';

    public function __construct(private RuleBasedNotificationParser $ruleParser) {}

    /**
     * @param  string  $text  已清理並全形轉半形的原文
     */
    public function rejectsBeforeAi(string $text): bool
    {
        if ($this->ruleParser->wholeTextRejectionReason($text) !== null) {
            return true;
        }

        if (preg_match(self::EXTRA_EXCLUDE_PATTERN, $text) !== 0 || preg_match(self::EXTRA_FOREIGN_PATTERN, $text) !== 0) {
            return true;
        }

        return preg_match(self::OVERSEAS_PATTERN, $text) !== 0 && preg_match(self::BARE_DOLLAR_PATTERN, $text) !== 0;
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
}
