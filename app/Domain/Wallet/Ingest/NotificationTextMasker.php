<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * 遮罩推播原文中的卡號／帳號／身分證類號碼（送 AI 前與寫入 title、備註前使用），金額與日期保留。
 */
final class NotificationTextMasker
{
    private const MASK = '****';

    /** 4 碼號碼後面接這些內容時視為金額或日期，不遮 */
    private const NOT_AMOUNT_OR_DATE = '(?![\d,.\/\-:]|\s*(?:元|NTD|TWD|NT\$|新台幣|新臺幣))';

    public static function mask(string $text): string
    {
        $patterns = [
            // 卡號格式：4 碼一組、3~4 組（可含 - 或空白、已部分遮罩的 X/*），日期 2026-11-15 不會命中
            '/(?<![\d*Xx])\d{4}(?:[- ]?[\dXx*]{4}){2,3}(?![\d*Xx])/u',
            // 10~19 位連續數字（帳號、未分隔卡號）
            '/(?<!\d)\d{10,19}(?!\d)/u',
            // 身分證／居留證：1 個英文字母 + 9 碼數字
            '/(?<![A-Za-z0-9])[A-Za-z]\d{9}(?![A-Za-z0-9])/u',
            // 關鍵字後的號碼：末四碼 1234、末 4 碼 4321、末四碼 (1234)、尾號1234、卡號：1234-56、帳號為 0012…
            '/(末\s*[四4]\s*碼|後\s*[四4]\s*碼|末碼|尾碼|尾號|尾數|卡號|帳號|帳戶|卡末)(\s*[:：為是]?\s*[(（]?\s*)[\dXx*][\dXx*\-]*/u',
            // Card ending 1234／ending in 1234／card no. 1234
            '/(card\s+ending(?:\s+in)?|ending\s+in|card\s+no\.?|card\s+number|acct\.?(?:\s+no\.?)?|account(?:\s+no\.?)?)(\s*[:#]?\s*[(]?\s*)[\dXx*][\dXx*\-]*/iu',
            // 卡組織／卡片字樣後接 4 碼：Visa 1234、JCB卡(4321)（VISA*1234 由下方星號規則處理）、卡片 5678、信用卡(5678)
            '/(?<![A-Za-z])(visa|master\s*card|master|jcb|amex|american\s+express|unionpay|diners|銀聯|卡片|信用卡|簽帳卡|金融卡)(\s*卡?\s*[:：]?\s*[(（]?\s*)\d{4}'.self::NOT_AMOUNT_OR_DATE.'/iu',
            // *1234、****1234
            '/\*+[\dXx*\-]*\d+/u',
            // xx1234、XXXX-1234（X 遮罩後接 4 碼；XX12 Burger 這類店名不遮）
            '/(?<![A-Za-z])[Xx]{2,}[Xx*\-]*\d{4}(?!\d)/u',
        ];

        $replacements = [
            self::MASK,
            self::MASK,
            self::MASK,
            '$1$2'.self::MASK,
            '$1$2'.self::MASK,
            '$1$2'.self::MASK,
            self::MASK,
            self::MASK,
        ];

        return (string) preg_replace($patterns, $replacements, $text);
    }

    /**
     * 移除 null byte 與其他控制字元（保留換行與 tab）；非法 UTF-8 回空字串。
     */
    public static function sanitize(string $text): string
    {
        return (string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{2028}\x{2029}\x{FEFF}]/u', '', $text);
    }

    /**
     * 單行化：控制字元移除後，換行／tab 轉成空白（寫入 title、note 用）。
     */
    public static function singleLine(string $text): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/u', ' ', self::sanitize($text)));
    }
}
