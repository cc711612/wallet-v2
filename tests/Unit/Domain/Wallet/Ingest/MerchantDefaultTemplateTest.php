<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTemplateRegistry;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 模板的 merchant_default：推播沒有商店名時用固定商店名；與 merchant 群組恰好擇一。
 */
class MerchantDefaultTemplateTest extends TestCase
{
    private const AMOUNT = '(?<amount>(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)';

    private function receivedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-15 20:53:00', 'Asia/Taipei');
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function registry(array $override = []): NotificationTemplateRegistry
    {
        return NotificationTemplateRegistry::fromArray([array_merge([
            'name' => 'default-merchant-probe',
            'pattern' => '信用卡消費'.self::AMOUNT.'元通知',
            'merchant_default' => '玉山信用卡消費',
        ], $override)]);
    }

    public function test_fixed_merchant_is_used_and_date_is_left_to_received_at(): void
    {
        $parsed = $this->registry()->match('信用卡消費1,280元通知', null, $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertSame(1280.0, $parsed->amount);
        $this->assertSame('玉山信用卡消費', $parsed->merchant);
        $this->assertNull($parsed->date, '沒有 date 群組時由 service 改用 received_at 的台北日期');
        $this->assertSame('template:default-merchant-probe', $parsed->parser);
    }

    public function test_merchant_default_is_trimmed(): void
    {
        $this->assertSame('玉山', $this->registry(['merchant_default' => '  玉山  '])->match('信用卡消費600元通知', null, $this->receivedAt())?->merchant);
    }

    public function test_amount_body_without_thousands_separator_also_matches(): void
    {
        $this->assertSame(1280.0, $this->registry()->match('信用卡消費1280元通知', null, $this->receivedAt())?->amount);
        $this->assertNull($this->registry()->match('信用卡消費1,23元通知', null, $this->receivedAt()));
    }

    /**
     * @return array<string, array{0:array<string, mixed>, 1:string}>
     */
    public static function invalid(): array
    {
        return [
            '兩者都有' => [['pattern' => '(?<merchant>[^\n]{1,10})信用卡消費'.self::AMOUNT.'元通知'], 'not both'],
            '兩者都沒有' => [['merchant_default' => null], 'merchant_default'],
            '空字串' => [['merchant_default' => '   '], '1-60 characters'],
            '超過 60 字' => [['merchant_default' => str_repeat('長', 61)], '1-60 characters'],
            '含換行' => [['merchant_default' => "玉山\n信用卡"], '1-60 characters'],
            '含零寬字元' => [['merchant_default' => "玉山\u{200B}"], '1-60 characters'],
            '含控制字元' => [['merchant_default' => "玉山\x07"], '1-60 characters'],
            '不是字串' => [['merchant_default' => 123], 'must be a string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalid')]
    public function test_invalid_merchant_default_definition_is_rejected(array $override, string $message): void
    {
        try {
            $this->registry($override)->templates();
            $this->fail('template should be rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertStringNotContainsString('信用卡消費', $exception->getMessage());
        }
    }

    public function test_sixty_characters_is_allowed(): void
    {
        $this->assertCount(1, $this->registry(['merchant_default' => str_repeat('長', 60)])->templates());
    }
}
