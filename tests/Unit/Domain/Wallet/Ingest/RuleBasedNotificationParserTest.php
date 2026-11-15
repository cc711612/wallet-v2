<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\ParsedExpense;
use App\Domain\Wallet\Ingest\RuleBasedNotificationParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuleBasedNotificationParserTest extends TestCase
{
    private function receivedAt(string $value = '2026-11-15 13:15:00'): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Asia/Taipei');
    }

    /**
     * @return array<string, array{0:string, 1:float, 2:string, 3:string|null}>
     */
    public static function notifications(): array
    {
        return [
            '消費金額 NT$ 千分位' => ['您的信用卡於全家便利商店消費金額NT$1,234，謝謝。', 1234.0, '全家便利商店', null],
            '刷卡金額 元' => ['【玉山】您在星巴克刷卡金額1,280元', 1280.0, '星巴克', null],
            'TWD 小數' => ['在 Apple.com/bill 刷卡 TWD 1,234.56', 1234.56, 'Apple.com/bill', null],
            '新台幣 + 前置日期時間' => ['您末四碼1234之信用卡於11/14 21:03於統一超商消費新台幣85元', 85.0, '統一超商', '2026-11-14'],
            'YYYY/MM/DD' => ['國泰世華：2026/11/13 您於全聯福利中心消費 NT$2,345', 2345.0, '全聯福利中心', '2026-11-13'],
            '商店欄位格式' => ["交易金額：NT\$999\n商店：UNIQLO 信義店\n交易日期：2026-11-12", 999.0, 'UNIQLO 信義店', '2026-11-12'],
            '民國年' => ['115/11/10 於誠品書店消費 NT$450', 450.0, '誠品書店', '2026-11-10'],
            '無日期用 null（由呼叫端改用 received_at）' => ['您在麥當勞刷卡消費 NT$199', 199.0, '麥當勞', null],
        ];
    }

    #[DataProvider('notifications')]
    public function test_it_parses_common_bank_formats(string $text, float $amount, string $merchant, ?string $date): void
    {
        $parsed = (new RuleBasedNotificationParser)->parse($text, $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertSame($amount, $parsed->amount);
        $this->assertSame($merchant, $parsed->merchant);
        $this->assertSame($date, $parsed->date);
        $this->assertSame(ParsedExpense::PARSER_RULE, $parsed->parser);
    }

    public function test_month_day_in_future_falls_back_to_previous_year(): void
    {
        $parsed = (new RuleBasedNotificationParser)->parse('12/31 您在全家刷卡 NT$50', $this->receivedAt('2027-01-01 00:10:00'));

        $this->assertNotNull($parsed);
        $this->assertSame('2026-12-31', $parsed->date);
    }

    public function test_invalid_date_is_ignored(): void
    {
        $parsed = (new RuleBasedNotificationParser)->parse('13/45 您在全家刷卡 NT$50', $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertNull($parsed->date);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function unparseable(): array
    {
        return [
            '沒有金額' => ['您在全家刷卡，感謝使用'],
            '沒有商店' => ['您的信用卡消費 NT$1,234'],
            '非消費訊息' => ['本期帳單已出帳，請於期限內繳款'],
            '金額為 0' => ['您在全家刷卡 NT$0'],
            '金額超過上限' => ['您在全家刷卡 NT$1,000,001'],
            '空字串' => ['   '],
        ];
    }

    #[DataProvider('unparseable')]
    public function test_it_returns_null_when_rules_do_not_match(string $text): void
    {
        $this->assertNull((new RuleBasedNotificationParser)->parse($text, $this->receivedAt()));
    }
}
