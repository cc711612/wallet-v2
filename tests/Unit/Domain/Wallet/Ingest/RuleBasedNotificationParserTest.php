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
     * @return array<string, array{0:string, 1:float|null, 2:string, 3:string|null}>
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
            '商店後接消費金額與卡號' => ['商店：全家 消費金額 1500 元 卡號末四碼 1234', 1500.0, '全家', null],
            '商店後接數字+元' => ['商店：全家 1,234元', 1234.0, '全家', null],
            '半形冒號與金額:' => ['商店:全家 金額:NT$100', 100.0, '全家', null],
            '商店後接日期' => ['商店：誠品 2026/11/14 刷卡 NT$300', 300.0, '誠品', '2026-11-14'],
            '7-ELEVEN' => ['您於7-ELEVEN消費 NT$85', 85.0, '7-ELEVEN', null],
            'PChome 24h購物' => ['您在PChome 24h購物刷卡 NT$1,299', 1299.0, 'PChome 24h購物', null],
            '全聯福利中心 一店' => ['您於全聯福利中心 一店消費 NT$500', 500.0, '全聯福利中心 一店', null],
            'Uniqlo-台北101' => ['您於Uniqlo-台北101消費 NT$990', 990.0, 'Uniqlo-台北101', null],
            '85度C' => ['您於85度C消費 NT$120', 120.0, '85度C', null],
            '裸 $ 無其他幣別線索視為台幣' => ['您在全家刷卡 $150', 150.0, '全家', null],
            '結尾全形字不被 trim 切壞 UTF-8' => ['您於誠品商行消費 NT$80', 80.0, '誠品商行', null],
            '商店名含空白不截斷' => ['商店：UNIQLO 信義店', null, '', null],
            '累計金額在前' => ['本期累計消費金額 NT$50,000，本筆刷卡金額 NT$1,200，於全家消費', 1200.0, '全家', null],
            '可用額度在後' => ['您於全家刷卡 NT$350，可用額度 NT$80,000', 350.0, '全家', null],
            '額度在前（無消費字樣）' => ['可用額度 NT$80,000；在全家刷卡 NT$350', 350.0, '全家', null],
        ];
    }

    #[DataProvider('notifications')]
    public function test_it_parses_common_bank_formats(string $text, ?float $amount, string $merchant, ?string $date): void
    {
        if ($amount === null) {
            // 只有商店沒有金額：不算命中
            $this->assertNull((new RuleBasedNotificationParser)->parse($text, $this->receivedAt()));

            return;
        }

        $parsed = (new RuleBasedNotificationParser)->parse($text, $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertSame($amount, $parsed->amount);
        $this->assertSame($merchant, $parsed->merchant);
        $this->assertSame($date, $parsed->date);
        $this->assertSame(ParsedExpense::PARSER_RULE, $parsed->parser);
    }

    public function test_existing_success_case_is_unchanged(): void
    {
        $parsed = (new RuleBasedNotificationParser)->parse('【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。', $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertSame(1234.0, $parsed->amount);
        $this->assertSame('全家便利商店', $parsed->merchant);
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
            '回饋入帳' => ['消費獲得回饋 NT$30 已入帳'],
            '繳款' => ['繳款 NT$12,345，商店：信用卡中心'],
            '還款' => ['您已還款 NT$5,000，於信用卡中心交易'],
            '取消' => ['您在全家刷卡 NT$120 交易已取消'],
            '交易失敗' => ['您在全家刷卡 NT$120 交易失敗'],
            '授權失敗' => ['於全家消費金額NT$120 授權失敗'],
            '未成功' => ['您在全家刷卡 NT$120 未成功'],
            '退刷' => ['您在全家退刷 NT$120'],
            '退款' => ['您在全家消費 NT$120 已退款'],
            '沖銷' => ['於全家消費 NT$120 已沖銷'],
            '撤銷' => ['於全家消費 NT$120 已撤銷'],
            '驗證碼' => ['在 Apple 使用 NT$1,000，驗證碼 123456'],
            'OTP' => ['您於 Amazon 刷卡 NT$2,000，OTP 為 654321'],
            '動態密碼' => ['在 Agoda 刷卡 NT$3,000，動態密碼 112233'],
            '刷卡滿額送' => ['刷卡滿 NT$3,000 送 300 元禮券，於全家消費'],
            '消費滿折' => ['消費滿 NT$1,111 折 111 元，在全家刷卡'],
            '優惠活動' => ['在全家刷卡 NT$100 享限時優惠'],
            '抽獎' => ['於全家消費 NT$500 即可參加抽獎'],
            '分期付款通知' => ['您於全國電子消費金額NT$36,000，分期付款 12 期，每期 NT$3,000'],
            '分3期' => ['您於Apple消費金額NT$30,000 分3期'],
            '第N期' => ['您在家樂福刷卡 NT$1,000 第2期'],
            '年費' => ['於信用卡中心扣年費 NT$1,200'],
            '遲繳' => ['您在本行刷卡 NT$300 遲繳費用'],
            '逾期' => ['逾期 NT$500，於本行消費'],
            '預借現金' => ['您在 ATM 預借現金 NT$5,000'],
            '提領現金' => ['您在 ATM 提領 NT$5,000'],
            '利息' => ['於本行消費 利息 NT$88'],
            '手續費' => ['在全家刷卡手續費 NT$15'],
            '帳單提醒' => ['本期帳單 NT$12,000，於全家消費'],
            '對帳單' => ['電子對帳單：於全家消費 NT$100'],
            'US$' => ['您在 Amazon 刷卡 US$25.99'],
            '$ + (USD)' => ['您在 Netflix 刷卡 $25.99 (USD)'],
            '日圓 元' => ['您於 唐吉訶德 消費 日圓 3,990 元'],
            'JPY' => ['您在 唐吉訶德 刷卡 JPY 3990'],
            'EUR' => ['您在 Zara 刷卡 EUR 49.9'],
            '美金' => ['您於 Apple 消費美金 9.99 元'],
            '日幣' => ['您於 Uniqlo 消費日幣 1,990 元'],
            '歐元' => ['您於 Zara 消費歐元 30 元'],
            '港幣' => ['您於 太古 消費港幣 200 元'],
            '人民幣' => ['您於 淘寶 消費人民幣 88 元'],
            '韓元' => ['您於 Olive Young 消費韓元 25,000 元'],
            '泰銖' => ['您於 7-11 消費泰銖 120 元'],
            '￥ 符號' => ['您在 Lawson 刷卡 ￥1,200'],
            '¥ 符號' => ['您在 Lawson 刷卡 ¥1,200'],
            '€ 符號' => ['您在 Zara 刷卡 €49'],
            '£ 符號' => ['您在 Boots 刷卡 £12'],
            '只有額度與累計金額' => ['本期累計 NT$50,000，可用額度 NT$80,000，於全家消費'],
        ];
    }

    #[DataProvider('unparseable')]
    public function test_it_returns_null_when_rules_do_not_match(string $text): void
    {
        $this->assertNull((new RuleBasedNotificationParser)->parse($text, $this->receivedAt()));
    }
}
