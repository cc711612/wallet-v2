<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\AiExpenseGuard;
use App\Domain\Wallet\Ingest\RuleBasedNotificationParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiExpenseGuardTest extends TestCase
{
    private function guard(): AiExpenseGuard
    {
        return new AiExpenseGuard(new RuleBasedNotificationParser);
    }

    /**
     * @return array<string, array{0:string, 1:bool}>
     */
    public static function texts(): array
    {
        return [
            '一般' => ['某某咖啡 250 感謝光臨', false],
            '裸 $ 無海外線索' => ['某某咖啡 $75', false],
            '結尾免責句也擋（保守）' => ['某某咖啡 250。如有疑問請洽客服，登錄 App 查看優惠', true],
            '爭議' => ['某某咖啡 250 爭議', true],
            '掛失' => ['某某咖啡 250 掛失', true],
            '攔截' => ['某某咖啡 250 攔截', true],
            'CA$' => ['CA$150', true],
            'US $' => ['US $49.99', true],
            'NZ$' => ['NZ$30', true],
            '美國 + $' => ['美國 $1', true],
            '海外在金額後' => ['$25.99 海外交易', true],
            'USD' => ['USD 25.99', true],
            'Dollars' => ['25.99 Dollars', true],
            '商店名 = 整句也無法縮小範圍' => ['小林拉麵二百五十元滿額贈好禮', true],
        ];
    }

    #[DataProvider('texts')]
    public function test_rejects_before_ai(string $text, bool $rejected): void
    {
        $this->assertSame($rejected, $this->guard()->rejectsBeforeAi($text));
    }

    /**
     * @return array<string, array{0:float, 1:string, 2:bool}>
     */
    public static function amounts(): array
    {
        return [
            '相同' => [250.0, '某某 250 元', true],
            '千分位' => [1350.0, '某某 NT$1,350', true],
            '.00' => [250.0, '某某 250.00', true],
            '小數' => [99.5, '某某 99.50', true],
            '多一位數' => [2500.0, '某某 250', false],
            '中文數字' => [250.0, '某某 二百五十', false],
            '部分數字' => [25.0, '某某 250', false],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amount_must_appear_in_text(float $amount, string $text, bool $expected): void
    {
        $this->assertSame($expected, $this->guard()->amountAppearsInText($amount, $text));
    }
}
