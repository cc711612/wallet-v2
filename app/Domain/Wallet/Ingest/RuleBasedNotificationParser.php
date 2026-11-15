<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use Carbon\CarbonImmutable;

/**
 * 以正規表示式解析銀行刷卡推播；金額與商店都命中才算成功，否則回 null 交給 AI 備援。
 * 新增銀行格式：在 AMOUNT_PATTERNS／MERCHANT_PATTERNS 追加 pattern（第 1 個 capture group 為值）。
 */
final class RuleBasedNotificationParser
{
    public const MAX_AMOUNT = 1000000;

    private const NUMBER = '(\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?)';

    private const CURRENCY = '(?:NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$)';

    /** @var array<int, string> 依序嘗試 */
    private const AMOUNT_PATTERNS = [
        // 消費金額 NT$1,234／刷卡金額1,234元／交易金額：TWD 1,234.5
        '/(?:消費|刷卡|交易|簽帳|授權)?金額\s*[:：為]?\s*'.self::CURRENCY.'?\s*'.self::NUMBER.'/u',
        // NT$1,234／TWD 1,234／新台幣85元
        '/'.self::CURRENCY.'\s*'.self::NUMBER.'/u',
        // 1,234元
        '/'.self::NUMBER.'\s*元/u',
    ];

    /** @var array<int, string> 依序嘗試 */
    private const MERCHANT_PATTERNS = [
        // 商店：全家便利商店／特店名稱:ABC
        '/(?:消費商店|交易商店|商店名稱|特店名稱|商店|特店|商家|消費地點)\s*[:：]\s*([^\n，,。;；]+)/u',
        // 在全家刷卡／在 全家 消費
        '/在\s*(.+?)\s*(?:刷卡|消費|交易|使用)/u',
        // 於全聯福利中心消費
        '/於\s*(.+?)\s*(?:刷卡|消費|交易|使用)/u',
    ];

    public function parse(string $text, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $amount = $this->parseAmount($text);
        $merchant = $this->parseMerchant($text);
        if ($amount === null || $merchant === null) {
            return null;
        }

        return new ParsedExpense($amount, $merchant, $this->parseDate($text, $receivedAt), ParsedExpense::PARSER_RULE);
    }

    private function parseAmount(string $text): ?float
    {
        foreach (self::AMOUNT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $amount = (float) str_replace(',', '', $matches[1]);
            if ($amount > 0 && $amount <= self::MAX_AMOUNT) {
                return $amount;
            }
        }

        return null;
    }

    private function parseMerchant(string $text): ?string
    {
        foreach (self::MERCHANT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $merchant = $this->cleanMerchant($matches[1]);
            if ($merchant !== null) {
                return $merchant;
            }
        }

        return null;
    }

    private function cleanMerchant(string $raw): ?string
    {
        $merchant = trim($raw);
        // 「於11/15 13:15於全聯消費」這類寫法：去掉前置日期時間與重複的介系詞
        $merchant = (string) preg_replace('/^(?:\d{2,4}[\/\-.])?\d{1,2}[\/\-.]\d{1,2}\s*(?:\d{1,2}:\d{2}(?::\d{2})?)?\s*/u', '', $merchant);
        $merchant = (string) preg_replace('/^(?:於|在)\s*/u', '', $merchant);
        $merchant = trim($merchant, " \t\n\r\0\x0B:：,，。;；");

        $length = mb_strlen($merchant);
        // 太長多半是誤抓整段句子，寧可交給 AI；商店名稱不可含金額字樣
        if ($length < 1 || $length > 60 || preg_match('/'.self::CURRENCY.'\s*\d/u', $merchant) === 1) {
            return null;
        }

        return $merchant;
    }

    private function parseDate(string $text, CarbonImmutable $receivedAt): ?string
    {
        // 西元 YYYY/MM/DD、YYYY-MM-DD、YYYY.MM.DD
        if (preg_match('/(?<!\d)(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?!\d)/u', $text, $m) === 1) {
            return $this->formatDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 民國 YYY/MM/DD
        if (preg_match('/(?<!\d)(1\d{2})\/(\d{1,2})\/(\d{1,2})(?!\d)/u', $text, $m) === 1) {
            return $this->formatDate((int) $m[1] + 1911, (int) $m[2], (int) $m[3]);
        }

        // MM/DD：年份取 received_at；若因此落在未來（例如 1 月收到 12/31 的通知）則改用前一年
        if (preg_match('/(?<![\d\/])(\d{1,2})\/(\d{1,2})(?![\d\/])/u', $text, $m) === 1) {
            $year = $receivedAt->year;
            $date = $this->formatDate($year, (int) $m[1], (int) $m[2]);
            if ($date !== null && $date > $receivedAt->addDay()->toDateString()) {
                $date = $this->formatDate($year - 1, (int) $m[1], (int) $m[2]);
            }

            return $date;
        }

        return null;
    }

    private function formatDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
