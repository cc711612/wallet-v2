<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * 遮罩推播原文中的卡號／帳號類數字（送 AI 前與寫入備註前使用），金額與日期保留。
 */
final class NotificationTextMasker
{
    private const MASK = '****';

    public static function mask(string $text): string
    {
        $patterns = [
            // 卡號格式：4 碼一組、3~4 組（可含 - 或空白、已部分遮罩的 X/*），日期 2026-11-15 不會命中
            '/(?<![\d*Xx])\d{4}(?:[- ]?[\dXx*]{4}){2,3}(?![\d*Xx])/u',
            // 10~19 位連續數字（帳號、未分隔卡號）
            '/(?<!\d)\d{10,19}(?!\d)/u',
            // 關鍵字後的號碼：末四碼 1234、末四碼 (1234)、尾號1234、卡號：1234-56、帳號為 0012…
            '/(末四碼|末4碼|後四碼|後4碼|末碼|尾碼|尾號|尾數|卡號|帳號|帳戶|卡末)(\s*[:：為是]?\s*[(（]?\s*)[\dXx*][\dXx*\-]*/u',
            // Card ending 1234／ending in 1234／card no. 1234
            '/(card\s+ending(?:\s+in)?|ending\s+in|card\s+no\.?|card\s+number|acct\.?(?:\s+no\.?)?|account(?:\s+no\.?)?)(\s*[:#]?\s*[(]?\s*)[\dXx*][\dXx*\-]*/iu',
            // 卡片 5678／信用卡(5678)：卡片字樣後直接接 4 碼數字；後面接「元」或千分位／小數（金額）則不遮
            '/(卡片|信用卡|簽帳卡|金融卡)(\s*[:：]?\s*[(（]?\s*)\d{4}(?![\d,.\/\-:]|\s*元)/u',
            // *1234、****1234、xx1234
            '/(?:\*|[Xx]{2,})[\dXx*\-]*\d+/u',
        ];

        $replacements = [
            self::MASK,
            self::MASK,
            '$1$2'.self::MASK,
            '$1$2'.self::MASK,
            '$1$2'.self::MASK,
            self::MASK,
        ];

        return (string) preg_replace($patterns, $replacements, $text);
    }
}
