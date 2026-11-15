<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use Illuminate\Support\Facades\Log;
use Throwable;

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

    /** 號碼本體：「1 2 3 4」逐字空白、中文數字「一二三四」，或連續的數字／X／*／- */
    private const NUMBER_BODY = '(?:\d(?:\s\d){3}(?!\d)|[零〇一二三四五六七八九]{4}|[\dXx*][\dXx*\-]*)';

    /** 號碼前方出現這些字代表是訂單編號／優惠碼，不視為個資 */
    private const ORDER_CONTEXT = '/(?:訂單|單號|編號|優惠碼|折扣碼|序號|兌換碼|order|coupon|promo|ref)[^卡帳]{0,6}$/iu';

    /** 號碼前方是發票語境（統一發票號碼 2 英文字母 + 8 碼），不遮 */
    private const INVOICE_CONTEXT = '/(?:發票|載具)[^卡帳證]{0,8}$/u';

    /** 前方是居留證語境：即使格式像發票也要遮 */
    private const RESIDENCE_CONTEXT = '/(?:居留證|居留|ARC|外僑)[^卡帳]{0,8}$/iu';

    /** 號碼前接台幣標記或後接「元」等：視為金額，不遮 */
    private const AMOUNT_PREFIX = '/(?:NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$)\s*$/u';

    private const AMOUNT_SUFFIX = '/^\s*(?:元|塊|NTD|TWD)/u';

    /** 號碼分組間的分隔：任何空白（含 NBSP、thin space、tab、換行）與 . / _ -，最多 3 個 */
    private const SEP = '[\p{Zs}\s.\/_\-]{1,3}';

    /** emoji 範圍：ZWJ（U+200D）只在兩側都是 emoji 時保留 */
    private const EMOJI = '[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]';

    public static function mask(string $text): string
    {
        $length = mb_strlen($text);
        $text = self::normalizeWidth(self::sanitize($text));
        $sep = self::SEP;

        // [pattern, 保留的前置群組數（0 = 整段換成 ****）, 跳過語境]
        $rules = [
            // Email：整段遮（會進 note 與 AI prompt）
            ['/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/u', 0, []],
            // 手機：0912-345-678、0912 345 678、09-1234-5678、+886 912 345 678
            ['/(?:\+?886(?:'.$sep.')?|(?<!\d)0)9\d{2}(?:'.$sep.')?\d{3}(?:'.$sep.')?\d{3}(?!\d)|(?<!\d)09'.$sep.'\d{4}'.$sep.'\d{4}(?!\d)/u', 0, []],
            // 前段已遮罩的卡號：**** **** **** 1234、XXXX-XXXX-XXXX-1234（末四碼一起遮）
            ['/(?:[*Xx]{4}'.$sep.'){1,3}[\dXx*]{4}(?![\d*Xx])/u', 0, []],
            // AMEX 4-6-5、Diners 4-6-4（分隔可為任意空白或 . / _ -）
            ['/(?<![\d*Xx])\d{4}(?:'.$sep.')?\d{6}(?:'.$sep.')?\d{4,5}(?![\d*Xx])/u', 0, ['order', 'amount']],
            // 卡號：4 碼一組 3~4 組，可再接 1~3 碼（銀聯 19 碼）；日期 2026-11-15 不會命中
            ['/(?<![\d*Xx])\d{4}(?:(?:'.$sep.')?[\dXx*]{4}){2,3}(?:(?:'.$sep.')?\d{1,3})?(?![\d*Xx])/u', 0, ['order', 'amount']],
            // 10 位以上連續數字（帳號、未分隔卡號、手機…）；金額（後接元／前接 NT$）與訂單編號不遮
            ['/(?<![A-Za-z\d])\d{10,}(?!\d)/u', 0, ['order', 'amount']],
            // 護照號碼（護照／Passport 語境，8~9 碼，可帶 1 個英文字母）
            ['/(護照(?:號碼)?|passport(?:\s*(?:no\.?|number))?)(\s*[:：#]?\s*)[A-Za-z]?\d{8,9}(?!\d)/iu', 2, []],
            // 身分證：1 英文字母 + 9 碼（數字間可有分隔）
            ['/(?<![A-Za-z0-9])[A-Za-z]\d(?:(?:'.$sep.')?\d){8}(?![A-Za-z0-9])/u', 0, ['order']],
            // 舊式居留證：2 英文字母 + 8 碼；發票語境（統一發票號碼同格式）不遮
            ['/(?<![A-Za-z0-9])[A-Za-z]{2}\d{8}(?![A-Za-z0-9])/u', 0, ['order', 'invoice']],
            // 關鍵字後的號碼：卡末4碼1234、末 4 碼 4321、末四碼 (1234)、末四碼 1 2 3 4、末四碼一二三四、尾號1234、卡號：1234-56
            ['/('.self::LAST_DIGITS_KEYWORDS.')(\s*[:：為是]?\s*[(（]?\s*)'.self::NUMBER_BODY.'/u', 2, []],
            // Card ending 1234／ending in 1234／card no. 1234
            ['/(card\s+ending(?:\s+in)?|ending\s+in|card\s+no\.?|card\s+number|acct\.?(?:\s+no\.?)?|account(?:\s+no\.?)?)(\s*[:#]?\s*[(]?\s*)[\dXx*][\dXx*\-]*/iu', 2, []],
            // 卡組織／卡片字樣後接 4 碼：Visa 1234、JCB卡(4321)、卡片 5678、您的卡 1234（VISA*1234 由星號規則處理）
            ['/(?<![A-Za-z])(visa|master\s*card|master|jcb|amex|american\s+express|unionpay|diners|銀聯|卡片|信用卡|簽帳卡|金融卡|的卡)(\s*卡?\s*[:：]?\s*[(（]?\s*)\d{4}'.self::NOT_AMOUNT_OR_DATE.'/iu', 2, []],
            // 統一編號
            ['/(統編|統一編號)(\s*[:：]?\s*)\d{8}(?!\d)/u', 2, []],
            // 地址門牌與樓層：信義路五段7號89樓 → 信義路五段****
            ['/(?<=[路街段巷弄道])\s*\d+(?:-\d+)?(?:之\d+)?號(?:\s*\d+樓(?:之\d+)?)?/u', 0, []],
            // *1234、****1234（atomic group 避免長串 * 回溯）
            ['/(?<!\*)(?>\*+)(?>[Xx*\-]*)\d[\dXx*\-]*/u', 0, []],
            // xx1234、XXXX-1234（X 遮罩後接 4 碼；XX12 Burger 這類店名不遮）
            ['/(?<![A-Za-z])(?>[Xx]{2,}[Xx*\-]*)\d{4}(?!\d)/u', 0, []],
        ];

        foreach ($rules as [$pattern, $keepGroups, $skips]) {
            $source = $text;
            $result = preg_replace_callback($pattern, static function (array $m) use ($source, $keepGroups, $skips): string {
                [$matched, $offset] = $m[0];
                if ($skips !== [] && self::inSkippedContext($source, $matched, (int) $offset, $skips)) {
                    return $matched;
                }

                $kept = '';
                for ($i = 1; $i <= $keepGroups; $i++) {
                    $kept .= (string) ($m[$i][0] ?? '');
                }

                return $kept.self::MASK;
            }, $text, -1, $count, PREG_OFFSET_CAPTURE);

            if ($result === null) {
                // 回溯上限等 preg 錯誤：寧可回空字串（呼叫端視為無內容），只記錯誤碼與長度，不記原文
                self::logPregError($length);

                return '';
            }

            $text = $result;
        }

        return $text;
    }

    /**
     * @param  array<int, string>  $skips  order／amount／invoice
     */
    private static function inSkippedContext(string $text, string $matched, int $offset, array $skips): bool
    {
        $prefix = mb_strcut($text, max(0, $offset - 60), min(60, $offset));
        $suffix = mb_strcut($text, $offset + strlen($matched), 30);

        if (in_array('order', $skips, true) && preg_match(self::ORDER_CONTEXT, $prefix) === 1) {
            return true;
        }

        if (in_array('amount', $skips, true) && (preg_match(self::AMOUNT_PREFIX, $prefix) === 1 || preg_match(self::AMOUNT_SUFFIX, $suffix) === 1)) {
            return true;
        }

        return in_array('invoice', $skips, true)
            && preg_match(self::INVOICE_CONTEXT, $prefix) === 1
            && preg_match(self::RESIDENCE_CONTEXT, $prefix) !== 1;
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
     * 移除控制字元（保留換行與 tab）與格式字元 \p{Cf}（零寬、bidi、WJ、SHY、BOM…）；非法 UTF-8 回空字串。
     * ZWJ（U+200D）只在兩側都是 emoji 時保留（👨‍👩‍👧 不被拆開），夾在數字之間的 ZWJ 一律移除，無法用來繞過遮罩。
     */
    public static function sanitize(string $text): string
    {
        $pattern = '/(?![\n\t\x{200D}])[\p{Cc}\p{Cf}\x{2028}\x{2029}]|(?<!'.self::EMOJI.')\x{200D}|\x{200D}(?!'.self::EMOJI.')/u';

        return (string) preg_replace($pattern, '', $text);
    }

    /**
     * 單行化：清理後，換行／tab 轉成空白（寫入 title、note 用）。
     */
    public static function singleLine(string $text): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/u', ' ', self::sanitize($text)));
    }

    private static function logPregError(int $length): void
    {
        try {
            Log::warning('NotificationTextMasker preg error', [
                'error' => preg_last_error_msg(),
                'length' => $length,
            ]);
        } catch (Throwable) {
            // 沒有 Laravel 容器（純單元測試）時不記錄
        }
    }
}
