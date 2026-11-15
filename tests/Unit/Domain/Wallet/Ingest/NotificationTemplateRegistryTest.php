<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTemplateRegistry;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ChecksIngestTemplateFixtures;
use Tests\TestCase;

class NotificationTemplateRegistryTest extends TestCase
{
    use ChecksIngestTemplateFixtures;

    private const OK = '【範例銀行】您的信用卡於11/14 19:20在鼎泰豐 信義店消費NT$1,350。';

    private function receivedAt(string $value = '2026-11-15 13:00:00'): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Asia/Taipei');
    }

    private function registry(array $override = []): NotificationTemplateRegistry
    {
        return NotificationTemplateRegistry::fromArray([array_merge(self::exampleTemplate(), $override)]);
    }

    public function test_default_config_contains_only_the_built_in_templates(): void
    {
        // 預設 config 內建 cathay-credit-purchase 與 esun-credit-purchase（都來自真實樣本），且通過載入與 lint
        $this->assertSame(['cathay-credit-purchase', 'esun-credit-purchase'], array_column((new NotificationTemplateRegistry)->templates(), 'name'));
        $this->assertNull((new NotificationTemplateRegistry)->match(self::OK, 'com.example.bank', $this->receivedAt()), '範例銀行格式不命中內建模板');
    }

    public function test_empty_template_list_matches_nothing(): void
    {
        $this->assertSame([], NotificationTemplateRegistry::fromArray([])->templates());
        $this->assertNull(NotificationTemplateRegistry::fromArray([])->match(self::OK, 'com.example.bank', $this->receivedAt()));
    }

    public function test_full_match_extracts_fields(): void
    {
        $parsed = $this->registry()->match(self::OK, 'com.example.bank', $this->receivedAt());

        $this->assertNotNull($parsed);
        $this->assertSame(1350.0, $parsed->amount);
        $this->assertSame('鼎泰豐 信義店', $parsed->merchant);
        $this->assertSame('2026-11-14', $parsed->date);
        $this->assertSame('template:example-bank-purchase', $parsed->parser);
    }

    public function test_surrounding_whitespace_is_allowed_but_extra_text_is_not(): void
    {
        $registry = $this->registry();

        $this->assertNotNull($registry->match("  \n".self::OK."\n ", 'com.example.bank', $this->receivedAt()));
        $this->assertNull($registry->match(self::OK.'如有疑問請洽客服。', 'com.example.bank', $this->receivedAt()));
        $this->assertNull($registry->match('通知：'.self::OK, 'com.example.bank', $this->receivedAt()));
    }

    /**
     * @return array<string, array{0:string|null, 1:string|null, 2:bool}>
     */
    public static function sources(): array
    {
        return [
            '相等' => ['com.example.bank', 'com.example.bank', true],
            '前綴' => ['com.example.bank', 'com.example.bank.lite', true],
            '不符' => ['com.example.bank', 'com.other.bank', false],
            '模板有 source、請求沒有' => ['com.example.bank', null, false],
            '模板沒限定 source' => [null, null, true],
        ];
    }

    #[DataProvider('sources')]
    public function test_source_filter(?string $templateSource, ?string $requestSource, bool $matches): void
    {
        $parsed = $this->registry(['source' => $templateSource])->match(self::OK, $requestSource, $this->receivedAt());

        $this->assertSame($matches, $parsed !== null);
    }

    public function test_month_day_falls_back_to_previous_year_when_in_future(): void
    {
        $parsed = $this->registry()->match(str_replace('11/14', '12/31', self::OK), 'com.example.bank', $this->receivedAt('2027-01-01 00:10:00'));

        $this->assertSame('2026-12-31', $parsed?->date);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function invalidValues(): array
    {
        return [
            '日期不合法' => [str_replace('11/14', '13/45', self::OK)],
            '金額 0' => [str_replace('1,350', '0', self::OK)],
            '金額超過上限' => [str_replace('1,350', '1,000,001', self::OK)],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_post_validation_rejects_invalid_values(string $text): void
    {
        $this->assertNull($this->registry()->match($text, 'com.example.bank', $this->receivedAt()));
    }

    public function test_loose_amount_and_merchant_groups_are_rejected_by_lint(): void
    {
        // 原本用來測「金額四捨五入」的寬鬆模板（\S+、[\d.]+），現在在載入時就被 lint 拒絕
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('rejected by lint');

        $this->registry(['source' => null, 'pattern' => '在(?<merchant>\\S+)消費(?<amount>[\\d.]+)元'])->templates();
    }

    /**
     * @return array<string, array{0:array<string, mixed>, 1:string}>
     */
    public static function invalidTemplates(): array
    {
        return [
            '沒有 name' => [['name' => null], 'kebab-case name'],
            'name 格式錯' => [['name' => 'Bad Name'], 'kebab-case name'],
            '缺 amount 群組' => [['pattern' => '(?<merchant>.+)'], 'must contain'],
            '缺 merchant 群組' => [['pattern' => '(?<amount>\\d+)'], 'must contain'],
            '非 TWD' => [['currency' => 'USD'], 'TWD only'],
            'regex 不合法' => [['pattern' => '(?<amount>\\d+)(?<merchant>['], 'not a valid regex'],
            'source 空字串' => [['source' => ''], 'source must be'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidTemplates')]
    public function test_invalid_template_definition_is_rejected(array $override, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->registry($override)->templates();
    }

    public function test_duplicate_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicated');

        NotificationTemplateRegistry::fromArray([self::exampleTemplate(), self::exampleTemplate()])->templates();
    }
}
