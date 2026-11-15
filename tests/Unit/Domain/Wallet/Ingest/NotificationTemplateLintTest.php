<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTemplateRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Concerns\ChecksIngestTemplateFixtures;
use Tests\TestCase;

/**
 * 模板 lint、命中後的二次排除、source 邊界、金額千分位與日期範圍、preg 錯誤記錄。
 */
class NotificationTemplateLintTest extends TestCase
{
    use ChecksIngestTemplateFixtures;

    private const MERCHANT = '(?<merchant>[^，。\n$]{1,40})';

    private const AMOUNT = '(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?)';

    private function receivedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-15 13:00:00', 'Asia/Taipei');
    }

    private function registry(string $pattern, ?string $source = null): NotificationTemplateRegistry
    {
        return NotificationTemplateRegistry::fromArray([['name' => 'lint-probe', 'source' => $source, 'pattern' => $pattern]]);
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function rejectedPatterns(): array
    {
        $m = self::MERCHANT;
        $a = self::AMOUNT;

        return [
            '括號不平衡跳出包裹群組' => ['x)|(?<merchant>[^\n]{1,10})消費NT\$'.$a.'|(?:y', 'not a valid regex'],
            '(*ACCEPT)' => [$m.'消費NT\$'.$a.'(*ACCEPT)', 'PCRE control verb'],
            '(*SKIP)' => [$m.'(*SKIP)消費NT\$'.$a, 'PCRE control verb'],
            '(?s)' => ['(?s)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '(?x)' => ['(?x)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '(?i:…)' => ['(?i:card)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '(?#註解)' => ['(?#c)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '(?R) 遞迴' => [$m.'消費NT\$'.$a.'(?R)?', 'group type not allowed'],
            '(?| 分支重設' => ['(?|a|b)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '其他具名群組' => ['(?<foo>x)'.$m.'消費NT\$'.$a, 'group type not allowed'],
            '.* 吞免責句' => [$m.'消費NT\$'.$a.'.*', 'unbounded wildcard'],
            '.+ 前綴' => ['.+'.$m.'消費NT\$'.$a, 'unbounded wildcard'],
            '.{1,}' => [$m.'消費NT\$'.$a.'.{1,}', 'unbounded wildcard'],
            '頂層交替' => ['foo|'.$m.'消費NT\$'.$a, 'top-level alternation'],
            'merchant .+（吃進「取消交易：」）' => ['(?<merchant>.+)消費NT\$'.$a, 'unbounded wildcard'],
            'merchant \S+' => ['(?<merchant>\S+)消費NT\$'.$a, 'merchant must be one character class'],
            'merchant 類別無上界' => ['(?<merchant>[\p{L}]+)消費NT\$'.$a, 'merchant must be one character class'],
            'merchant 上界超過 60' => ['(?<merchant>[\p{L}]{1,100})消費NT\$'.$a, 'merchant quantifier'],
            'merchant 類別用 \S' => ['(?<merchant>[\S]{1,10})消費NT\$'.$a, 'must not use'],
            'merchant 否定類別沒排除換行' => ['(?<merchant>[^，]{1,10})消費NT\$'.$a, 'must exclude newlines'],
            'amount 寬鬆' => [$m.'消費NT\$(?<amount>[\d,]+)', 'amount must be'],
            'amount 小數三位' => [$m.'消費NT\$(?<amount>\d+(?:\.\d{1,3})?)', 'amount must be'],
            'amount 出現兩次（PCRE 不允許重複名稱）' => [$m.$a.'消費NT\$'.$a, 'not a valid regex'],
            'amount 只出現在字元類別或註解字面裡' => ['[(?<amount>]'.$m.'消費', 'exactly once'],
        ];
    }

    #[DataProvider('rejectedPatterns')]
    public function test_pattern_is_rejected_at_load(string $pattern, string $message): void
    {
        try {
            $this->registry($pattern)->templates();
            $this->fail('pattern should be rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            // 例外訊息不含 pattern 內容
            $this->assertStringNotContainsString('消費NT', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function acceptedPatterns(): array
    {
        return [
            '範例模板' => [(string) self::exampleTemplate()['pattern']],
            '正向類別' => ['商店：(?<merchant>[\p{L}\p{N}\s\-_&\'’・·./()（）]{1,40}) 消費NT\$(?<amount>\d+(?:\.\d{1,2})?)'],
            '否定類別含 \s' => ['(?<merchant>[^\s，。：:;；]{1,40})消費(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?)元'],
            '包起來的交替與 lookahead' => ['(?:刷卡|消費)(?!失敗)(?<merchant>[^\n，]{1,20}?)NT\$(?<amount>\d+(?:\.\d{1,2})?)'],
            '跳脫的括號與點' => ['\(通知\)\.(?<merchant>[^\n]{1,20})NT\$(?<amount>\d+(?:\.\d{1,2})?)'],
        ];
    }

    #[DataProvider('acceptedPatterns')]
    public function test_pattern_passes_lint(string $pattern): void
    {
        $this->assertCount(1, $this->registry($pattern)->templates());
    }

    /**
     * 通過 lint 但偏寬的模板：命中後由整段原文的強排除／外幣檢查擋下。
     *
     * @return array<string, array{0:string}>
     */
    public static function secondLineCases(): array
    {
        return [
            '前綴取消' => ['取消交易：全家消費NT$85'],
            '退款前綴' => ['您於全家的退款：原消費NT$85'],
            '外幣前綴' => ['USD 在 AMAZON消費NT$30'],
            '交易失敗前綴' => ['交易失敗 全家消費NT$85'],
            '未核准' => ['未核准 全家消費NT$85'],
            '退刷' => ['退刷 全家消費NT$85'],
        ];
    }

    #[DataProvider('secondLineCases')]
    public function test_wide_template_match_is_blocked_by_second_line_screen(string $text): void
    {
        $registry = $this->registry('(?<merchant>[^\n]{1,40}?)消費NT\$(?<amount>\d+(?:\.\d{1,2})?)');

        $this->assertNull($registry->match($text, null, $this->receivedAt()));
        $this->assertNotNull($registry->match('全家消費NT$85', null, $this->receivedAt()), '對照：正常原文仍命中');
    }

    /**
     * @return array<string, array{0:string|null, 1:bool}>
     */
    public static function sources(): array
    {
        return [
            '相等' => ['com.bank', true],
            '前綴 + 點' => ['com.bank.lite', true],
            '前綴 + 斜線' => ['com.bank/notify', true],
            '前綴但沒有邊界' => ['com.bankfake', false],
            '大小寫不同' => ['COM.BANK', false],
            '前後空白會 trim' => ['  com.bank  ', true],
            '含零寬字元' => ["com.bank\u{200B}", false],
            '含控制字元' => ["com.bank\x07", false],
            '沒帶' => [null, false],
            '空字串' => ['', false],
        ];
    }

    #[DataProvider('sources')]
    public function test_source_boundary_match(?string $source, bool $matches): void
    {
        $registry = $this->registry(self::MERCHANT.'消費NT\$'.self::AMOUNT, 'com.bank');

        $this->assertSame($matches, $registry->match('全家消費NT$85', $source, $this->receivedAt()) !== null);
    }

    public function test_template_source_with_spaces_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry(self::MERCHANT.'消費NT\$'.self::AMOUNT, ' com.bank')->templates();
    }

    /**
     * @return array<string, array{0:string, 1:bool}>
     */
    public static function rawAmounts(): array
    {
        return [
            '1,234' => ['1,234', true],
            '1234.5' => ['1234.5', true],
            '1,23' => ['1,23', false],
            '1,2,3,4' => ['1,2,3,4', false],
            ',,85' => [',,85', false],
            '85.555' => ['85.555', false],
            '1 350' => ['1 350', false],
        ];
    }

    #[DataProvider('rawAmounts')]
    public function test_amount_post_validation_checks_thousands_format(string $raw, bool $valid): void
    {
        $method = new ReflectionMethod(NotificationTemplateRegistry::class, 'toExpense');
        $parsed = $method->invoke($this->registry(self::MERCHANT.self::AMOUNT), 'lint-probe', ['amount' => $raw, 'merchant' => '全家'], $this->receivedAt());

        $this->assertSame($valid, $parsed !== null);
    }

    /**
     * received_at = 2026-11-15 13:00（台北）
     *
     * @return array<string, array{0:string, 1:string|null}>
     */
    public static function datedTexts(): array
    {
        return [
            '當天' => ['2026/11/15', '2026-11-15'],
            '隔天（+1 天內）' => ['2026/11/16', '2026-11-16'],
            '超過 +1 天' => ['2026/11/17', null],
            '一年內' => ['2025/11/16', '2025-11-16'],
            '超過一年' => ['2025/11/14', null],
            '遠未來' => ['2030/01/01', null],
            '遠過去' => ['1999/1/1', null],
            '民國年一年內' => ['115/11/14', '2026-11-14'],
        ];
    }

    #[DataProvider('datedTexts')]
    public function test_dates_with_year_must_be_within_range(string $date, ?string $expected): void
    {
        $registry = $this->registry('(?<date>\d{3,4}/\d{1,2}/\d{1,2}) '.self::MERCHANT.'消費NT\$'.self::AMOUNT);
        $parsed = $registry->match($date.' 全家消費NT$85', null, $this->receivedAt());

        $this->assertSame($expected, $parsed?->date);
    }

    public function test_preg_error_is_logged_without_text_and_fails_closed(): void
    {
        Log::spy();
        $registry = $this->registry(self::MERCHANT.'消費NT\$'.self::AMOUNT);
        $registry->templates(); // 先在正常設定下編譯並通過 lint
        $text = '秘密商店'.str_repeat('a', 30).'消費NT$85';
        $limit = ini_get('pcre.backtrack_limit');
        $jit = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '1');
        ini_set('pcre.jit', '0');

        try {
            $parsed = $registry->match($text, null, $this->receivedAt());
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
            ini_set('pcre.jit', (string) $jit);
        }

        $this->assertNull($parsed);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'NotificationTemplateRegistry preg error'
                && $context['template'] === 'lint-probe'
                && $context['error'] === 'Backtrack limit exhausted'
                && is_int($context['length'])
                && ! str_contains((string) json_encode($context, JSON_UNESCAPED_UNICODE), '秘密');
        });
    }
}
