<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

use InvalidArgumentException;

/**
 * 模板 pattern 的靜態檢查（載入時執行）。目的：讓模板只能「窄」，不能用萬用字元吞掉取消、退款、外幣等內容。
 *
 * 規則（任一違反就丟 InvalidArgumentException，訊息只含模板名稱與規則，不含 pattern）：
 * - 不得有 .* .+ .{n,}（無上界的萬用字元）
 * - 不得有 PCRE 控制動詞 (*…)、行內選項 (?s) (?x) (?i)…、註解 (?#…)、遞迴／子程序／條件／分支重設 (?R) (?1) (?&) (?() (?|、原子群組 (?>
 *   允許的群組開頭：( 、(?: 、(?= 、(?! 、(?<= 、(?<! 、(?<amount> (?<merchant> (?<date> (?<time>
 * - 括號必須平衡；頂層不得有 |（交替要用 (?:…|…) 包起來）
 * - amount 只能是 \d{1,3}(?:,\d{3})*(?:\.\d{1,2})? 或 \d+(?:\.\d{1,2})?
 * - merchant 只能是「一個字元類別＋有上界的量詞」，例如 [^，。\n$]{1,40}；上界 ≤ 60；
 *   不得用 \S \W \D 等大寫類別；否定類別 [^…] 必須排除換行（含 \n 或 \s）
 * - amount、merchant 各恰好一次；date、time 最多一次；不得有其他具名群組
 */
final class NotificationTemplateLinter
{
    public const MERCHANT_MAX_LENGTH = 60;

    /** @var array<int, string> */
    private const AMOUNT_BODIES = [
        '\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?',
        '\d+(?:\.\d{1,2})?',
    ];

    /** @var array<int, string> */
    private const GROUP_NAMES = ['amount', 'merchant', 'date', 'time'];

    public static function lint(string $name, string $pattern): void
    {
        $fail = static fn (string $rule) => new InvalidArgumentException("ingest template '{$name}' pattern rejected by lint: {$rule}");

        // 跳脫字元與字元類別先換成等長的佔位字元，之後的結構檢查才不會被 \( 或 [(] 誤導
        $noEscape = (string) preg_replace_callback('/\\\\./su', static fn (array $m): string => str_repeat('E', strlen($m[0])), $pattern);
        $skeleton = (string) preg_replace_callback('/\[\^?\]?[^\]]*\]/u', static fn (array $m): string => str_repeat('C', strlen($m[0])), $noEscape);

        if (preg_match('/\.(?:[*+]|\{\d*,\})/', $skeleton) === 1) {
            throw $fail('unbounded wildcard (.* .+ .{n,})');
        }
        if (str_contains($skeleton, '(*')) {
            throw $fail('PCRE control verb (*…)');
        }

        $depth = 0;
        $groups = [];
        $length = strlen($skeleton);
        for ($i = 0; $i < $length; $i++) {
            $char = $skeleton[$i];
            if ($char === '(') {
                $rest = substr($skeleton, $i + 1, 20);
                if (str_starts_with($rest, '?')) {
                    if (preg_match('/^\?<(amount|merchant|date|time)>/', $rest, $g) === 1) {
                        $groups[] = ['name' => $g[1], 'start' => $i + strlen($g[0]) + 1, 'depth' => $depth];
                    } elseif (preg_match('/^\?(?::|=|!|<=|<!)/', $rest) !== 1) {
                        throw $fail('group type not allowed (inline flags, comments, recursion, named groups other than amount/merchant/date/time…)');
                    }
                }
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    throw $fail('unbalanced parentheses');
                }
            } elseif ($char === '|' && $depth === 0) {
                throw $fail('top-level alternation; wrap it in (?:…|…)');
            }
        }
        if ($depth !== 0) {
            throw $fail('unbalanced parentheses');
        }

        $counts = array_count_values(array_column($groups, 'name'));
        if (($counts['amount'] ?? 0) !== 1 || ($counts['merchant'] ?? 0) !== 1) {
            throw $fail('amount and merchant groups must appear exactly once');
        }
        if (($counts['date'] ?? 0) > 1 || ($counts['time'] ?? 0) > 1) {
            throw $fail('date/time groups may appear at most once');
        }

        foreach ($groups as $group) {
            $body = substr($pattern, $group['start'], self::groupEnd($skeleton, $group['start']) - $group['start']);
            if ($group['name'] === 'amount' && ! in_array($body, self::AMOUNT_BODIES, true)) {
                throw $fail('amount must be \d{1,3}(?:,\d{3})*(?:\.\d{1,2})? or \d+(?:\.\d{1,2})?');
            }
            if ($group['name'] === 'merchant') {
                self::lintMerchant($body, $fail);
            }
        }
    }

    /**
     * @param  callable(string): InvalidArgumentException  $fail
     */
    private static function lintMerchant(string $body, callable $fail): void
    {
        if (preg_match('/^\[(\^?)((?:\\\\.|[^\]\\\\])+)\]\{(\d+),(\d+)\}\??$/su', $body, $m) !== 1) {
            throw $fail('merchant must be one character class with a bounded quantifier, e.g. [^，。\n$]{1,40}');
        }

        [, $negated, $class, $min, $max] = $m;
        if ((int) $min < 1 || (int) $max > self::MERCHANT_MAX_LENGTH || (int) $min > (int) $max) {
            throw $fail('merchant quantifier must be {1..60}');
        }
        if (preg_match('/\\\\[SWDXRNHVC]/', $class) === 1) {
            throw $fail('merchant class must not use \S \W \D \X \R \N \H \V \C');
        }
        if ($negated === '^' && ! str_contains($class, '\n') && ! str_contains($class, '\s')) {
            throw $fail('negated merchant class must exclude newlines (\n or \s)');
        }
    }

    private static function groupEnd(string $skeleton, int $start): int
    {
        $depth = 0;
        $length = strlen($skeleton);
        for ($i = $start; $i < $length; $i++) {
            if ($skeleton[$i] === '(') {
                $depth++;
            } elseif ($skeleton[$i] === ')') {
                if ($depth === 0) {
                    return $i;
                }
                $depth--;
            }
        }

        return $length;
    }
}
