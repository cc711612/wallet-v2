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
            // 關鍵字後的號碼：末四碼 1234、卡號：1234-56、帳號為 0012…
            '/(末四碼|末4碼|後四碼|後4碼|末碼|尾碼|尾數|卡號|帳號|帳戶|卡末)(\s*[:：為是]?\s*)[\dXx*][\dXx*\-]*/u',
            // *1234、****1234、xx1234
            '/(?:\*|[Xx]{2,})[\dXx*\-]*\d+/u',
        ];

        $replacements = [
            self::MASK,
            self::MASK,
            '$1$2'.self::MASK,
            self::MASK,
        ];

        return (string) preg_replace($patterns, $replacements, $text);
    }
}
