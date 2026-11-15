<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use Carbon\CarbonImmutable;

/**
 * 以正規表示式解析銀行刷卡推播。
 *
 * 判定流程（結構性規則，避免「全文掃關鍵字」誤殺結尾的免責／行銷句）：
 * 1. 清理控制字元、全形轉半形。
 * 2. 切成子句（。！？；換行、【標題】、非千分位的逗號）。
 * 3. 正向閘門：第一個「同時有消費語境詞與（非累計／額度語境的）金額」的子句才是交易子句；沒有就回 null。
 * 4. 排除：強排除（取消／失敗／退款／分期／提款…）看交易子句與前後各一個子句；
 *    軟排除（行銷／帳務字樣）只看交易子句，且先去掉括號內容與商店名；外幣標記看交易子句與前後各一個子句。
 * 5. 金額與商店都有才算成功，否則回 null 交給 AI 備援或回 400。
 */
final class RuleBasedNotificationParser
{
    public const MAX_AMOUNT = 1000000;

    private const NUMBER = '(\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?)';

    private const CURRENCY = '(?:NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$)';

    /** 消費語境詞（正向閘門）；「商店：」等欄位標籤也代表這是一筆交易通知 */
    private const CONSUMPTION_PATTERN = '/消費|刷卡|授權|交易|付款|支付|簽帳|扣款|使用|商店\s*[:：]|商店名稱|特店名稱|特店\s*[:：]/u';

    /**
     * 強排除：交易子句或其前後各一個子句出現即不是一筆成功的消費。
     *
     * @var array<int, string>
     */
    private const STRONG_EXCLUDE_PATTERNS = [
        // 取消／失敗／退款／沖正類
        '/取消|失敗|未成功|不成功|未完成|未核准|未通過|未授權|婉拒|拒絕|逾時|退刷|退款|退貨|沖銷|沖正|沖回|撤銷|作廢|更正|盜刷|暫停|停用|停卡|撥款|停止續訂/u',
        // 預先授權（飯店、加油等，金額不是實際消費）
        '/預先授權|預授權/u',
        // 驗證碼類
        '/驗證碼|認證碼|OTP|動態密碼|一次性密碼|簡訊密碼/iu',
        // 分期：即使是真實刷卡同時分期，也不自動記帳
        '/分期|期付款|每期|第\s*\d+\s*期|分\s*\d+\s*期|期數/u',
        // 現金與轉帳類：提款／提領／預借現金、繳款／還款、入帳、代扣代繳、自動加值、收款分攤
        '/提款|提領|預借現金|領現|繳款|還款|入帳|返現|代扣|代繳|扣繳|自動扣款|自動加值|收款|分攤/u',
    ];

    /**
     * 軟排除：只看交易子句（去掉括號內容與商店名後），行銷與帳務字樣。
     *
     * @var array<int, string>
     */
    private const SOFT_EXCLUDE_PATTERNS = [
        // 行銷／促銷：滿 NT$3,000 送、滿額、N 折、折抵、優惠、抽獎…
        '/滿\s*'.self::CURRENCY.'?\s*\d|滿額|滿.{0,12}(?:送|折|抵|贈)|\d+(?:\.\d+)?\s*折|折\s*\d|現折|抵\s*\d|折抵|扣抵|回饋|紅利|點數|POINTS|獲得|贈送|優惠|加碼|抽獎|限時|活動期間|登錄|好禮/iu',
        // 帳務類：年費、遲繳、逾期、利息、手續費、帳單
        '/年費|遲繳|逾期|延滯|利息|循環信用|手續費|帳單|對帳單/u',
    ];

    /**
     * 外幣標記：交易子句或前後各一個子句出現即放棄（不猜匯率、不寫成 TWD）。
     * NT$、新台幣、TWD、元視為台幣；裸 $ 沒有其他幣別線索時也視為台幣。
     *
     * @var array<int, string>
     */
    private const FOREIGN_CURRENCY_PATTERNS = [
        // 幣別代碼（前後不接英文字母）、U.S.D、Dollar(s)、帶前綴的 $
        '/(?<![A-Za-z])(?:USD|JPY|EUR|GBP|HKD|CNY|RMB|KRW|THB|SGD|AUD|CAD|MYR|VND|CHF|NZD|PHP|IDR|INR|MOP|U\.S\.D\.?|dollars?)(?![A-Za-z])|(?<![A-Za-z])(?:US|HK|S|A|C|NZ|AU|CN)\$/iu',
        // 中文幣別
        '/美金|美元|日圓|日幣|日元|歐元|英鎊|港幣|港元|人民幣|韓元|韓圜|泰銖|新加坡幣|新幣|澳幣|澳元|加幣|加元|令吉|越南盾|披索|瑞士法郎|紐幣|印尼盾|盧比|澳門幣|外幣/u',
        // 幣別符號
        '/[￥¥€£₩฿円₫]/u',
        // 海外／國外 + 裸 $：視為外幣
        '/(?:海外|國外).{0,20}(?<![A-Za-z])\$\s*\d/u',
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
        '/(?:消費|刷卡|交易|簽帳|授權|付款|支付)金額\s*[:：為]?\s*'.self::CURRENCY.'?\s*'.self::NUMBER.'/u',
        // 刷卡 NT$1,234／消費新台幣85元／付款 NT$45
        '/(?:消費|刷卡|簽帳|授權|使用|付款|支付|扣款)\s*'.self::CURRENCY.'\s*'.self::NUMBER.'/u',
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
        '/在\s*(.+?)\s*(?:刷卡|消費|交易|使用|付款|支付)/u',
        // 於全聯福利中心消費／於路易莎咖啡付款
        '/於\s*(.+?)\s*(?:刷卡|消費|交易|使用|付款|支付)/u',
    ];

    /** 商店名遇到這些內容就截斷：金額／交易字樣、幣別、數字+元、卡號字樣、日期 */
    private const MERCHANT_STOP_PATTERN = '/\s*(?:金額|消費|刷卡|授權|交易|付款|支付|NT\$|NTD|TWD|新台幣|新臺幣|台幣|臺幣|\$|卡號|末四碼|末4碼|後四碼|尾號|尾數|卡末|\d[\d,]*(?:\.\d+)?\s*元|\d{4}[\/\-.]\d{1,2}|(?<!\d)\d{1,2}\/\d{1,2}|\d{1,2}:\d{2})/u';

    public function parse(string $text, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return null;
        }

        $transaction = $this->findTransaction($this->clauses($text));
        if ($transaction === null) {
            return null;
        }

        $merchant = $this->parseMerchant($text);
        if ($merchant === null || $this->rejectionIn($transaction['clauses'], $transaction['index'], $merchant) !== null) {
            return null;
        }

        return new ParsedExpense($transaction['amount'], $merchant, $this->parseDate($text, $receivedAt), ParsedExpense::PARSER_RULE);
    }

    /**
     * 供 AI 路徑交叉檢查：原文命中排除規則或外幣標記時回傳原因（excluded／foreign_currency），否則 null。
     * $includeSoft=false 時只看強排除與外幣（送 AI 前的預檢）。
     * 範圍同規則解析（交易子句與前後子句）；找不到交易子句時檢查整段原文。
     */
    public function rejectionReason(string $text, ?string $merchant = null, bool $includeSoft = true): ?string
    {
        $text = $this->normalize($text);
        $clauses = $this->clauses($text);
        $transaction = $this->findTransaction($clauses);

        if ($transaction === null) {
            return $this->rejectionIn([$text], 0, $merchant, $includeSoft);
        }

        return $this->rejectionIn($clauses, $transaction['index'], $merchant, $includeSoft);
    }

    /**
     * 對整段原文做強排除、軟排除與外幣檢查（不縮小範圍、不剔除商店名）；AI 路徑使用的保守檢查。
     */
    public function wholeTextRejectionReason(string $text): ?string
    {
        return $this->rejectionIn([$this->normalize($text)], 0, null);
    }

    private function normalize(string $text): string
    {
        return trim(NotificationTextMasker::normalizeWidth(NotificationTextMasker::sanitize($text)));
    }

    /**
     * @return array<int, string>
     */
    private function clauses(string $text): array
    {
        // 【標題】獨立成子句；逗號只在不是千分位時才切
        $text = str_replace(['【', '】'], ["\n【", "】\n"], $text);
        $parts = preg_split('/[。！？!?；;\n]+|[，、]|(?<!\d),|,(?!\d{3}(?!\d))/u', $text);

        return array_values(array_filter(
            array_map('trim', is_array($parts) ? $parts : [$text]),
            static fn (string $clause): bool => $clause !== ''
        ));
    }

    /**
     * 正向閘門：回傳第一個同時含消費語境詞與金額的子句。
     *
     * @param  array<int, string>  $clauses
     * @return array{clauses: array<int, string>, index: int, amount: float}|null
     */
    private function findTransaction(array $clauses): ?array
    {
        foreach ($clauses as $index => $clause) {
            if (preg_match(self::CONSUMPTION_PATTERN, $clause) !== 1) {
                continue;
            }

            $amount = $this->parseAmount($clause);
            if ($amount !== null) {
                return ['clauses' => $clauses, 'index' => $index, 'amount' => $amount];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $clauses
     */
    private function rejectionIn(array $clauses, int $index, ?string $merchant, bool $includeSoft = true): ?string
    {
        $transactionClause = $clauses[$index];
        $nearby = implode("\n", array_slice($clauses, max(0, $index - 1), $index === 0 ? 2 : 3));

        if ($this->matchesAny(self::FOREIGN_CURRENCY_PATTERNS, $nearby)) {
            return 'foreign_currency';
        }

        if ($this->matchesAny(self::STRONG_EXCLUDE_PATTERNS, $nearby)) {
            return 'excluded';
        }

        if (! $includeSoft) {
            return null;
        }

        // 軟排除不看括號補充說明（例如「(含優惠折抵)」）與商店名（例如「優惠廚房」）
        $softScope = (string) preg_replace('/[(（][^)）]*[)）]/u', '', $transactionClause);
        if ($merchant !== null && $merchant !== '') {
            $softScope = str_replace($merchant, '', $softScope);
        }

        return $this->matchesAny(self::SOFT_EXCLUDE_PATTERNS, $softScope) ? 'excluded' : null;
    }

    /**
     * preg 執行錯誤時視為命中（fail-closed）。
     *
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) !== 0) {
                return true;
            }
        }

        return false;
    }

    private function parseAmount(string $clause): ?float
    {
        foreach (self::AMOUNT_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $clause, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                if ($this->inExcludedAmountContext($clause, (int) $match[0][1])) {
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
     * 檢查金額前方同一子句（最多 10 字）是否為累計／額度語境。
     */
    private function inExcludedAmountContext(string $clause, int $byteOffset): bool
    {
        $prefix = mb_substr(substr($clause, 0, $byteOffset), -10);

        return $this->matchesAny(self::AMOUNT_CONTEXT_EXCLUDE_PATTERNS, $prefix);
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
