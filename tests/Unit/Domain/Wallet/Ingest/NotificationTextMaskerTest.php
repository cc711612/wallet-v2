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
            '尾號' => ['尾號1234', '尾號****'],
            '末四碼括號' => ['末四碼 (1234)', '末四碼 (****)'],
            '卡號末四碼括號' => ['卡號末四碼(1234)', '卡號末四碼(****)'],
            'Card ending' => ['Card ending 1234', 'Card ending ****'],
            'card ending in 小寫' => ['your card ending in 9876 was charged', 'your card ending in **** was charged'],
            '卡片 4 碼' => ['卡片 5678', '卡片 ****'],
            '信用卡括號' => ['信用卡(5678)於全家消費', '信用卡(****)於全家消費'],
            'Visa 4 碼' => ['Visa 1234 消費', 'Visa **** 消費'],
            'VISA 星號' => ['VISA*1234', 'VISA****'],
            'JCB 卡括號' => ['JCB卡(4321)', 'JCB卡(****)'],
            'MasterCard' => ['MasterCard 8765', 'MasterCard ****'],
            '末 4 碼 空白' => ['末 4 碼 4321', '末 4 碼 ****'],
            '身分證' => ['持卡人 A123456789 您好', '持卡人 **** 您好'],
            'xx 遮罩後接 4 碼' => ['卡號xx1234', '卡號****'],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_masks_card_and_account_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, NotificationTextMasker::mask($input));
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function untouched(): array
    {
        return [
            'NT$ 千分位' => ['NT$1,234'],
            '數字+元' => ['1234元'],
            '數字 空白 元' => ['5000 元'],
            '西元日期' => ['2026/11/15'],
            '月日' => ['11/15'],
            '卡片消費金額' => ['卡片消費 5678 元'],
            '卡片後接金額' => ['信用卡 1234元'],
            '卡片後接日期' => ['信用卡 2026/11/15 消費'],
            '卡片後接千分位' => ['卡片 5,678 元'],
            '卡片後接 NTD' => ['卡片 1500 NTD'],
            '信用卡後接 TWD' => ['信用卡 1234 TWD'],
            'Visa 後接金額' => ['Visa 1,234元'],
            '店名含 XX+數字' => ['XX12 Burger'],
            '店名 85度C' => ['85度C'],
        ];
    }

    #[DataProvider('untouched')]
    public function test_amounts_and_dates_are_not_masked(string $text): void
    {
        $this->assertSame($text, NotificationTextMasker::mask($text));
    }

    public function test_sanitize_removes_null_byte_and_control_characters(): void
    {
        $this->assertSame("全家\n消費", NotificationTextMasker::sanitize("全\0家\x07\n消\x1B費\u{200B}"));
        $this->assertSame('全家 消費', NotificationTextMasker::singleLine("全\0家\r\n消費\t"));
        $this->assertSame('', NotificationTextMasker::sanitize("\xff\xfe"));
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
