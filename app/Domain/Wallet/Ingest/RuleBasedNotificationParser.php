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

    /**
     * 原文命中任一 pattern 即不是一筆消費（回饋入帳、繳款、取消／失敗／退款、驗證碼…），規則解析直接放棄。
     *
     * @var array<int, string>
     */
    private const EXCLUDE_PATTERNS = [
        '/回饋|入帳|現金回饋|紅利/u',
        '/繳款|還款|扣繳|自動扣款|已收到.{0,6}款/u',
        '/取消|失敗|未成功|未完成|退刷|退款|退貨|沖銷|沖正|撤銷|拒絕/u',
        '/驗證碼|認證碼|OTP|動態密碼|一次性密碼|簡訊密碼/iu',
        // 行銷／促銷：刷卡滿 NT$3,000 送…、消費滿 NT$1,111 折 111 元
        '/滿\s*'.self::CURRENCY.'?\s*\d|滿額|滿.{0,12}(?:送|折|抵|贈)|折\s*\d|現折|抵\s*\d|折抵|回饋活動|優惠|加碼|抽獎|限時|活動期間|登錄|好禮/u',
        // 分期：即使是真實刷卡同時分期，也不自動記帳（金額與每期款項無法可靠判斷）
        '/分期|期付款|每期|第\s*\d+\s*期|分\s*\d+\s*期|期數/u',
        // 帳務類：年費、遲繳／逾期、預借現金／提領、利息、手續費、帳單／對帳單提醒
        '/年費|遲繳|逾期|延滯|提領|預借現金|領現|利息|循環信用|手續費|帳單|對帳單/u',
    ];

    /**
     * 原文出現非台幣的幣別標記即放棄（不猜匯率、不寫成 TWD）。
     * NT$、新台幣、TWD、元視為台幣；裸 $ 沒有其他幣別線索時也視為台幣。
     *
     * @var array<int, string>
     */
    private const FOREIGN_CURRENCY_PATTERNS = [
        // 幣別代碼（前後不接英文字母）與帶前綴的 $
        '/(?<![A-Za-z])(?:USD|JPY|EUR|GBP|HKD|CNY|RMB|KRW|THB|SGD|AUD|CAD|MYR|VND|CHF|NZD|PHP|IDR|INR|MOP)(?![A-Za-z])|(?<![A-Za-z])(?:US|HK|S|A|C|NZ|AU)\$/iu',
        // 中文幣別
        '/美金|美元|日圓|日幣|日元|歐元|英鎊|港幣|港元|人民幣|韓元|韓圜|泰銖|新加坡幣|新幣|澳幣|澳元|加幣|加元|令吉|越南盾|披索|瑞士法郎|紐幣|印尼盾|盧比|澳門幣|外幣/u',
        // 幣別符號
        '/[￥¥€£₩฿円]/u',
    ];

    /**
     * 金額前方（同一子句內）出現這些字，代表是累計／額度／帳單金額，不是本筆消費。
     *
     * @var array<int, string>
     */
    private const AMOUNT_CONTEXT_EXCLUDE_PATTERNS = [
        '/累計|額度|帳單|應繳|最低|餘額|可用|上限|合計|總計/u',
    ];

    /**
     * 依優先序分層嘗試；同一層取第一個不在排除語境的金額（第 1 個 capture group 為金額）。
     *
     * @var array<int, string>
     */
    private const AMOUNT_PATTERNS = [
        // 消費金額 NT$1,234／刷卡金額1,234元／授權金額：TWD 1,234.5
        '/(?:消費|刷卡|交易|簽帳|授權)金額\s*[:：為]?\s*'.self::CURRENCY.'?\s*'.self::NUMBER.'/u',
        // 刷卡 NT$1,234／消費新台幣85元／使用 NT$1,000
        '/(?:消費|刷卡|簽帳|授權|使用)\s*'.self::CURRENCY.'\s*'.self::NUMBER.'/u',
        // 金額:NT$100／金額 1,234 元
        '/金額\s*[:：為]?\s*'.self::CURRENCY.'?\s*'.self::NUMBER.'/u',
        // NT$1,234／TWD 1,234／新台幣85元
        '/'.self::CURRENCY.'\s*'.self::NUMBER.'/u',
        // 1,234元
        '/'.self::NUMBER.'\s*元/u',
    ];

    /**
     * 商店名稱的開頭樣式（第 1 個 capture group 為候選商店，之後再以 MERCHANT_STOP_PATTERN 截斷）。
     *
     * @var array<int, string>
     */
    private const MERCHANT_PATTERNS = [
        // 商店：全家便利商店／特店名稱:ABC（全形、半形冒號；千分位逗號不算分隔，交給截斷處理）
        '/(?:消費商店|交易商店|商店名稱|特店名稱|商店|特店|商家|消費地點)\s*[:：]\s*((?:[^\n，,。;；]|(?<=\d),(?=\d))+)/u',
        // 在全家刷卡／在 全家 消費
        '/在\s*(.+?)\s*(?:刷卡|消費|交易|使用)/u',
        // 於全聯福利中心消費
        '/於\s*(.+?)\s*(?:刷卡|消費|交易|使用)/u',
    ];

    /** 商店名遇到這些內容就截斷：金額／交易字樣、幣別、數字+元、卡號字樣、日期 */
    private const MERCHANT_STOP_PATTERN = '/\s*(?:金額|消費|刷卡|授權|交易|NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$|卡號|末四碼|末4碼|後四碼|尾號|尾數|卡末|\d[\d,]*(?:\.\d+)?\s*元|\d{4}[\/\-.]\d{1,2}|(?<!\d)\d{1,2}\/\d{1,2}|\d{1,2}:\d{2})/u';

    public function parse(string $text, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        $text = trim($text);
        if ($text === '' || $this->isExcluded($text) || $this->hasForeignCurrency($text)) {
            return null;
        }

        $amount = $this->parseAmount($text);
        $merchant = $this->parseMerchant($text);
        if ($amount === null || $merchant === null) {
            return null;
        }

        return new ParsedExpense($amount, $merchant, $this->parseDate($text, $receivedAt), ParsedExpense::PARSER_RULE);
    }

    private function hasForeignCurrency(string $text): bool
    {
        foreach (self::FOREIGN_CURRENCY_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isExcluded(string $text): bool
    {
        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function parseAmount(string $text): ?float
    {
        foreach (self::AMOUNT_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                if ($this->inExcludedAmountContext($text, (int) $match[0][1])) {
                    continue;
                }

                $amount = (float) str_replace(',', '', $match[1][0]);
                if ($amount > 0 && $amount <= self::MAX_AMOUNT) {
                    return $amount;
                }
            }
        }

        return null;
    }

    /**
     * 檢查金額前方同一子句（最多 10 字、不跨標點）是否為累計／額度語境。
     */
    private function inExcludedAmountContext(string $text, int $byteOffset): bool
    {
        $prefix = mb_substr(substr($text, 0, $byteOffset), -10);
        $parts = preg_split('/[\n，,。;；!！]/u', $prefix);
        $clause = is_array($parts) ? (string) end($parts) : $prefix;

        foreach (self::AMOUNT_CONTEXT_EXCLUDE_PATTERNS as $pattern) {
            if (preg_match($pattern, $clause) === 1) {
                return true;
            }
        }

        return false;
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
        // 同一行後面的金額、卡號、日期等內容不屬於商店名
        $parts = preg_split(self::MERCHANT_STOP_PATTERN, $merchant, 2);
        $merchant = is_array($parts) ? (string) $parts[0] : $merchant;
        // trim() 的 charlist 以 byte 處理，含全形字會切壞 UTF-8，改用 /u 正規表示式
        $merchant = (string) preg_replace('/^[\s:：,，。;；(（]+|[\s:：,，。;；(（]+$/u', '', $merchant);

        $length = mb_strlen($merchant);
        // 太長多半是誤抓整段句子，寧可交給 AI
        if ($length < 1 || $length > 60) {
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
