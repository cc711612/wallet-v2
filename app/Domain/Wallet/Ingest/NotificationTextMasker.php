<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * 推播原文的清理、全形轉半形與遮罩（卡號／帳號／身分證／手機），金額與日期保留。
 */
final class NotificationTextMasker
{
    private const MASK = '****';

    /** 4 碼號碼後面接這些內容時視為金額或日期，不遮 */
    private const NOT_AMOUNT_OR_DATE = '(?![\d,.\/\-:]|\s*(?:元|NTD|TWD|NT\$|新台幣|新臺幣))';

    /** 末四碼類關鍵字（較長的寫法放前面；「卡末」只在後面直接接號碼時命中） */
    private const LAST_DIGITS_KEYWORDS = '卡號\s*末\s*[四4]\s*碼|卡號\s*後\s*[四4]\s*碼|卡?\s*末\s*[四4]\s*碼|後\s*[四4]\s*碼|末碼|尾碼|尾號|尾數|卡號|帳號|帳戶|卡末';

    /** 號碼本體：「1 2 3 4」這種逐字空白的寫法，或連續的數字／X／*／- */
    private const NUMBER_BODY = '(?:\d(?:\s\d){3}(?!\d)|[\dXx*][\dXx*\-]*)';

    public static function mask(string $text): string
    {
        $text = self::normalizeWidth(self::sanitize($text));

        $rules = [
            // 手機：0912-345-678、0912 345 678、+886 912 345 678
            ['/(?:\+?886[-\s]?|(?<!\d)0)9\d{2}[-\s]?\d{3}[-\s]?\d{3}(?!\d)/u', self::MASK],
            // 卡號格式：4 碼一組、3~4 組（可含 - 或空白、已部分遮罩的 X/*），日期 2026-11-15 不會命中
            ['/(?<![\d*Xx])\d{4}(?:[- ]?[\dXx*]{4}){2,3}(?![\d*Xx])/u', self::MASK],
            // 10~19 位連續數字（帳號、未分隔卡號、手機）
            ['/(?<!\d)\d{10,19}(?!\d)/u', self::MASK],
            // 身分證／居留證：1 個英文字母 + 9 碼數字
            ['/(?<![A-Za-z0-9])[A-Za-z]\d{9}(?![A-Za-z0-9])/u', self::MASK],
            // 關鍵字後的號碼：卡末4碼1234、末 4 碼 4321、末四碼 (1234)、末四碼 1 2 3 4、尾號1234、卡號：1234-56
            ['/('.self::LAST_DIGITS_KEYWORDS.')(\s*[:：為是]?\s*[(（]?\s*)'.self::NUMBER_BODY.'/u', '$1$2'.self::MASK],
            // Card ending 1234／ending in 1234／card no. 1234
            ['/(card\s+ending(?:\s+in)?|ending\s+in|card\s+no\.?|card\s+number|acct\.?(?:\s+no\.?)?|account(?:\s+no\.?)?)(\s*[:#]?\s*[(]?\s*)[\dXx*][\dXx*\-]*/iu', '$1$2'.self::MASK],
            // 卡組織／卡片字樣後接 4 碼：Visa 1234、JCB卡(4321)、卡片 5678、您的卡 1234（VISA*1234 由星號規則處理）
            ['/(?<![A-Za-z])(visa|master\s*card|master|jcb|amex|american\s+express|unionpay|diners|銀聯|卡片|信用卡|簽帳卡|金融卡|的卡)(\s*卡?\s*[:：]?\s*[(（]?\s*)\d{4}'.self::NOT_AMOUNT_OR_DATE.'/iu', '$1$2'.self::MASK],
            // 統一編號
            ['/(統編|統一編號)(\s*[:：]?\s*)\d{8}(?!\d)/u', '$1$2'.self::MASK],
            // *1234、****1234
            ['/\*+[\dXx*\-]*\d+/u', self::MASK],
            // xx1234、XXXX-1234（X 遮罩後接 4 碼；XX12 Burger 這類店名不遮）
            ['/(?<![A-Za-z])[Xx]{2,}[Xx*\-]*\d{4}(?!\d)/u', self::MASK],
        ];

        foreach ($rules as [$pattern, $replacement]) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * 全形英數、空白與常見符號轉半形（「１，２３４」→「1,234」、「ＵＳＤ」→「USD」）。
     * 中文標點（，：；（）。）保留，只有夾在數字之間的全形逗號／句點轉成千分位與小數點。
     */
    public static function normalizeWidth(string $text): string
    {
        $text = mb_convert_kana($text, 'rns', 'UTF-8');
        $text = strtr($text, [
            '＄' => '$', '＊' => '*', '－' => '-', '／' => '/', '＃' => '#', '＋' => '+',
            '＠' => '@', '％' => '%', '＆' => '&', '＿' => '_', '＝' => '=',
        ]);

        return (string) preg_replace(['/(?<=\d)，(?=\d{3}(?!\d))/u', '/(?<=\d)．(?=\d)/u'], [',', '.'], $text);
    }

    /**
     * 移除控制字元（保留換行與 tab）與所有格式字元 \p{Cf}（零寬、bidi、WJ、SHY、BOM…）；非法 UTF-8 回空字串。
     */
    public static function sanitize(string $text): string
    {
        return (string) preg_replace('/(?![\n\t])[\p{Cc}\p{Cf}\x{2028}\x{2029}]/u', '', $text);
    }

    /**
     * 單行化：清理後，換行／tab 轉成空白（寫入 title、note 用）。
     */
    public static function singleLine(string $text): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/u', ' ', self::sanitize($text)));
    }
}
