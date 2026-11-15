<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Ingest;

/**
 * 推播解析結果：金額、商店、日期（Y-m-d，原文沒有時為 null）與解析來源。
 */
final class ParsedExpense
{
    public const PARSER_RULE = 'rule';

    public const PARSER_AI = 'ai';

    public function __construct(
        public readonly float $amount,
        public readonly string $merchant,
        public readonly ?string $date,
        public readonly string $parser,
    ) {}
}
