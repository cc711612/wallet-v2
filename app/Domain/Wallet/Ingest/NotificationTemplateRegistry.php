<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * 推播模板白名單：原文必須「整段」符合某個模板（首尾錨定）才會被解析成一筆消費。
 * 模板定義在 config/ingest_templates.php；格式錯誤或沒通過 lint（NotificationTemplateLinter）會丟 InvalidArgumentException。
 * 命中後還會對整段原文做強排除與外幣檢查（ExpenseTextScreen），作為模板過寬時的第二道防線。
 */
class NotificationTemplateRegistry
{
    public const PARSER_PREFIX = 'template:';

    private const DELIMITER = "\x01";

    /** @var array<int, array{name:string, regex:string, currency:string, source:string|null}>|null */
    private ?array $compiled = null;

    /**
     * @param  array<int, array<string, mixed>>|null  $templates  null 時讀 config('ingest_templates.templates')
     */
    private ExpenseTextScreen $screen;

    public function __construct(private ?array $templates = null, ?ExpenseTextScreen $screen = null)
    {
        $this->screen = $screen ?? new ExpenseTextScreen;
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    public static function fromArray(array $templates): self
    {
        return new self($templates);
    }

    /**
     * @return array<int, array{name:string, regex:string, currency:string, source:string|null}>
     */
    public function templates(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        /** @var array<int, array<string, mixed>> $raw */
        $raw = $this->templates ?? (array) config('ingest_templates.templates', []);

        $compiled = [];
        $names = [];
        foreach (array_values($raw) as $index => $template) {
            $compiled[] = $this->compile($template, $index, $names);
        }

        return $this->compiled = $compiled;
    }

    /**
     * 依 source 篩選候選模板，回傳第一個整段命中且通過後置驗證的結果；都不命中回 null。
     *
     * @param  string  $text  已清理控制字元並全形轉半形的原文
     */
    public function match(string $text, ?string $source, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        $source = $this->normalizeSource($source);

        foreach ($this->templates() as $template) {
            if (! $this->sourceMatches($template['source'], $source)) {
                continue;
            }

            $matched = preg_match($template['regex'], $text, $m);
            if ($matched === false) {
                // preg 錯誤（例如回溯上限）：視為不命中，只記錯誤與長度，不記原文
                $this->logPregError($template['name'], $text);

                continue;
            }
            if ($matched !== 1) {
                continue;
            }

            $parsed = $this->toExpense($template['name'], $m, $receivedAt);
            if ($parsed === null) {
                continue;
            }

            // 第二道防線：整段原文有強排除或外幣線索就不記帳（即使模板命中）
            return $this->screen->rejects($text) ? null : $parsed;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<int, string>  $names
     * @return array{name:string, regex:string, currency:string, source:string|null}
     */
    private function compile(mixed $template, int $index, array &$names): array
    {
        if (! is_array($template)) {
            throw new InvalidArgumentException("ingest template #{$index} must be an array");
        }

        $name = $template['name'] ?? null;
        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9\-]{1,80}$/', $name) !== 1) {
            throw new InvalidArgumentException("ingest template #{$index} needs a kebab-case name");
        }
        if (in_array($name, $names, true)) {
            throw new InvalidArgumentException("ingest template name '{$name}' is duplicated");
        }
        $names[] = $name;

        $pattern = $template['pattern'] ?? null;
        if (! is_string($pattern) || $pattern === '') {
            throw new InvalidArgumentException("ingest template '{$name}' needs a pattern");
        }
        if (! str_contains($pattern, '(?<amount>') || ! str_contains($pattern, '(?<merchant>')) {
            throw new InvalidArgumentException("ingest template '{$name}' pattern must contain (?<amount>…) and (?<merchant>…)");
        }
        if (str_contains($pattern, self::DELIMITER)) {
            throw new InvalidArgumentException("ingest template '{$name}' pattern contains an invalid character");
        }

        // 單獨編譯一次，確認 pattern 本身合法（括號不平衡等在包裹前就會失敗）
        if (@preg_match(self::DELIMITER.$pattern.self::DELIMITER.'u', '') === false) {
            throw new InvalidArgumentException("ingest template '{$name}' pattern is not a valid regex");
        }
        NotificationTemplateLinter::lint($name, $pattern);

        $currency = strtoupper((string) ($template['currency'] ?? 'TWD'));
        if ($currency !== 'TWD') {
            throw new InvalidArgumentException("ingest template '{$name}' currency {$currency} is not supported (TWD only)");
        }

        $source = $template['source'] ?? null;
        if ($source !== null && (! is_string($source) || $source === '' || $source !== trim($source) || preg_match('/[\p{Cc}\p{Cf}\s]/u', $source) !== 0)) {
            throw new InvalidArgumentException("ingest template '{$name}' source must be a non-empty string without spaces or control characters");
        }

        // 首尾錨定：原文多出或少了模板之外的內容都不命中
        $regex = self::DELIMITER.'\A\s*(?:'.$pattern.')\s*\z'.self::DELIMITER.'u';
        if (@preg_match($regex, '') === false) {
            throw new InvalidArgumentException("ingest template '{$name}' pattern is not a valid regex");
        }

        return ['name' => $name, 'regex' => $regex, 'currency' => $currency, 'source' => $source];
    }

    /**
     * request 的 source：trim；含控制或零寬字元視為沒帶。
     */
    private function normalizeSource(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $source = trim($source);
        if ($source === '' || preg_match('/[\p{Cc}\p{Cf}]/u', $source) !== 0) {
            return null;
        }

        return $source;
    }

    /**
     * 精確相等，或「前綴＋邊界」（前綴後必須是 . 或 /）：com.bank 命中 com.bank、com.bank.lite，不命中 com.bankfake。
     */
    private function sourceMatches(?string $templateSource, ?string $requestSource): bool
    {
        if ($templateSource === null) {
            return true;
        }

        if ($requestSource === null) {
            return false;
        }

        if ($requestSource === $templateSource) {
            return true;
        }

        return str_starts_with($requestSource, $templateSource)
            && in_array($requestSource[strlen($templateSource)], ['.', '/'], true);
    }

    /**
     * @param  array<int|string, string>  $m
     */
    private function toExpense(string $name, array $m, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        // 千分位格式必須正確：1,23、1,2,3,4、,,85 都拒絕
        $rawAmount = (string) ($m['amount'] ?? '');
        if (preg_match('/^(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?$/', $rawAmount) !== 1) {
            return null;
        }

        $amount = round((float) str_replace(',', '', $rawAmount), 2);
        if ($amount <= 0 || $amount > RuleBasedNotificationParser::MAX_AMOUNT) {
            return null;
        }

        $merchant = NotificationTextMasker::singleLine((string) ($m['merchant'] ?? ''));
        $merchantLength = mb_strlen($merchant);
        if ($merchantLength < 1 || $merchantLength > 255) {
            return null;
        }

        $date = null;
        $rawDate = trim((string) ($m['date'] ?? ''));
        if ($rawDate !== '') {
            $date = $this->parseDate($rawDate, $receivedAt);
            if ($date === null) {
                return null;
            }
        }

        return new ParsedExpense($amount, $merchant, $date, self::PARSER_PREFIX.$name);
    }

    /**
     * 支援 YYYY/MM/DD、YYYY-MM-DD、YYYY.MM.DD、民國 YYY/MM/DD、MM/DD、M月D日；不合法回 null。
     */
    private function parseDate(string $raw, CarbonImmutable $receivedAt): ?string
    {
        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $raw, $d) === 1) {
            return $this->withinRange($this->formatDate((int) $d[1], (int) $d[2], (int) $d[3]), $receivedAt);
        }

        if (preg_match('/^(1\d{2})\/(\d{1,2})\/(\d{1,2})$/', $raw, $d) === 1) {
            return $this->withinRange($this->formatDate((int) $d[1] + 1911, (int) $d[2], (int) $d[3]), $receivedAt);
        }

        if (preg_match('/^(\d{1,2})(?:\/|月)(\d{1,2})日?$/u', $raw, $d) === 1) {
            // 沒有年份：取 received_at 的年份；若因此落在未來（1 月收到 12/31 的通知）改用前一年
            $date = $this->formatDate($receivedAt->year, (int) $d[1], (int) $d[2]);
            if ($date !== null && $date > $receivedAt->addDay()->toDateString()) {
                $date = $this->formatDate($receivedAt->year - 1, (int) $d[1], (int) $d[2]);
            }

            return $date;
        }

        return null;
    }

    /**
     * 有年份的日期：須在 received_at 前 1 年內，且不晚於 received_at 後 1 天。
     */
    private function withinRange(?string $date, CarbonImmutable $receivedAt): ?string
    {
        if ($date === null) {
            return null;
        }

        $earliest = $receivedAt->subYear()->toDateString();
        $latest = $receivedAt->addDay()->toDateString();

        return $date >= $earliest && $date <= $latest ? $date : null;
    }

    private function logPregError(string $template, string $text): void
    {
        try {
            Log::warning('NotificationTemplateRegistry preg error', [
                'template' => $template,
                'error' => preg_last_error_msg(),
                'length' => mb_strlen($text),
            ]);
        } catch (Throwable) {
            // 沒有 Laravel 容器（純單元測試）時不記錄
        }
    }

    private function formatDate(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
