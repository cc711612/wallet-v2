<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTextMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationTextMaskerTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function cases(): array
    {
        return [
            '末四碼' => ['您末四碼 1234 之卡片', '您末四碼 **** 之卡片'],
            '末四碼無空白' => ['末四碼1234消費', '末四碼****消費'],
            '卡號含分隔' => ['卡號 1234-5678-9012-3456 消費', '卡號 **** 消費'],
            '卡號部分遮罩' => ['卡號：4311-****-****-1234', '卡號：****'],
            '無關鍵字的卡號' => ['您 1234 5678 9012 3456 的卡', '您 **** 的卡'],
            '星號末碼' => ['卡片*1234於全家消費', '卡片****於全家消費'],
            '連續星號末碼' => ['卡片 ****1234', '卡片 ****'],
            '帳號長數字' => ['轉入帳戶 00123456789012', '轉入帳戶 ****'],
            '尾數' => ['尾數5678', '尾數****'],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_masks_card_and_account_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, NotificationTextMasker::mask($input));
    }

    public function test_amounts_and_dates_are_kept(): void
    {
        $text = '您末四碼1234之信用卡於2026/11/15 13:15於全家消費NT$1,234.50，另 2026-11-14 刷卡 TWD 987 元，11/13 消費 85元';

        $masked = NotificationTextMasker::mask($text);

        $this->assertStringNotContainsString('1234之', $masked);
        $this->assertStringContainsString('末四碼****之信用卡', $masked);
        $this->assertStringContainsString('2026/11/15 13:15', $masked);
        $this->assertStringContainsString('NT$1,234.50', $masked);
        $this->assertStringContainsString('2026-11-14', $masked);
        $this->assertStringContainsString('TWD 987 元', $masked);
        $this->assertStringContainsString('11/13 消費 85元', $masked);
    }
}
