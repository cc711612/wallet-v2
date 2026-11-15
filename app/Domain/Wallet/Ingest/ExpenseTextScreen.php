<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * 整段原文的第二道防線：強排除（取消、失敗、退款、退刷、未核准、分期、預先授權…）與外幣線索。
 * 模板命中後與 AI 備援都會用；命中就不記帳。這是輔助防線，不是主要判斷依據。
 */
class ExpenseTextScreen
{
    /** 規則清單以外的外幣線索：CA$／CA $、US $、NZ$、AU$、SG$、C $ 等（符號與 $ 間可有空白）、幣別代碼與符號 */
    private const EXTRA_FOREIGN_PATTERNS = [
        '/(?<![A-Za-z])(?:CA|US|NZ|AU|HK|CN|SG|A|C|S)\s*\$/iu',
        '/(?<![A-Za-z])(?:MXN|TRY|CAD|AUD|RM|BRL|ZAR|SEK|NOK|DKK|PLN|RUB|AED|SAR)(?![A-Za-z])/u',
        '/[₱₹₽₺₫]|盧布|比索|瑞郎|英磅|跨境|(?<=\d)\s*(?:歐|盾)/u',
    ];

    /** 原文任何位置出現美國／海外／國外，且金額是裸 $（不是 NT$） */
    private const OVERSEAS_PATTERN = '/美國|海外|國外/u';

    private const BARE_DOLLAR_PATTERN = '/(?<![A-Za-z])\$\s*\d/u';

    public function __construct(private ?RuleBasedNotificationParser $ruleParser = null)
    {
        $this->ruleParser ??= new RuleBasedNotificationParser;
    }

    /**
     * @param  string  $text  已清理並全形轉半形的原文
     */
    public function rejects(string $text): bool
    {
        if ($this->ruleParser?->wholeTextRejectionReason($text, false) !== null) {
            return true;
        }

        return $this->hasForeignClue($text);
    }

    public function hasForeignClue(string $text): bool
    {
        foreach (self::EXTRA_FOREIGN_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) !== 0) {
                return true;
            }
        }

        return preg_match(self::OVERSEAS_PATTERN, $text) !== 0 && preg_match(self::BARE_DOLLAR_PATTERN, $text) !== 0;
    }
}
