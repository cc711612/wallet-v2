<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 推播模板白名單：原文必須「整段」符合某個模板（首尾錨定）才會被解析成一筆消費。
 * 模板定義在 config/ingest_templates.php；格式錯誤（缺具名群組、非 TWD、regex 不合法）會丟 InvalidArgumentException。
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
    public function __construct(private ?array $templates = null) {}

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
        foreach ($this->templates() as $template) {
            if (! $this->sourceMatches($template['source'], $source)) {
                continue;
            }

            if (preg_match($template['regex'], $text, $m) !== 1) {
                continue;
            }

            $parsed = $this->toExpense($template['name'], $m, $receivedAt);
            if ($parsed !== null) {
                return $parsed;
            }
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

        $currency = strtoupper((string) ($template['currency'] ?? 'TWD'));
        if ($currency !== 'TWD') {
            throw new InvalidArgumentException("ingest template '{$name}' currency {$currency} is not supported (TWD only)");
        }

        $source = $template['source'] ?? null;
        if ($source !== null && (! is_string($source) || $source === '')) {
            throw new InvalidArgumentException("ingest template '{$name}' source must be a non-empty string");
        }

        // 首尾錨定：原文多出或少了模板之外的內容都不命中
        $regex = self::DELIMITER.'\A\s*(?:'.$pattern.')\s*\z'.self::DELIMITER.'u';
        if (@preg_match($regex, '') === false) {
            throw new InvalidArgumentException("ingest template '{$name}' pattern is not a valid regex");
        }

        return ['name' => $name, 'regex' => $regex, 'currency' => $currency, 'source' => $source];
    }

    private function sourceMatches(?string $templateSource, ?string $requestSource): bool
    {
        if ($templateSource === null) {
            return true;
        }

        return $requestSource !== null && str_starts_with($requestSource, $templateSource);
    }

    /**
     * @param  array<int|string, string>  $m
     */
    private function toExpense(string $name, array $m, CarbonImmutable $receivedAt): ?ParsedExpense
    {
        $rawAmount = str_replace([',', ' '], '', (string) ($m['amount'] ?? ''));
        if (preg_match('/^\d+(?:\.\d+)?$/', $rawAmount) !== 1) {
            return null;
        }

        $amount = round((float) $rawAmount, 2);
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
            return $this->formatDate((int) $d[1], (int) $d[2], (int) $d[3]);
        }

        if (preg_match('/^(1\d{2})\/(\d{1,2})\/(\d{1,2})$/', $raw, $d) === 1) {
            return $this->formatDate((int) $d[1] + 1911, (int) $d[2], (int) $d[3]);
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

    private function formatDate(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
