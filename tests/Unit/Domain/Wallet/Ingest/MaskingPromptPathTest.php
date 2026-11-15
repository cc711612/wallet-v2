<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Gemini\Services\GeminiService;
use App\Domain\Wallet\Ingest\AiNotificationParser;
use App\Domain\Wallet\Ingest\NotificationTextMasker;
use Carbon\CarbonImmutable;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MaskingCases;
use Tests\TestCase;

/**
 * 遮罩案例走「送 Gemini 的 prompt」路徑：AiNotificationParser 組 prompt 前的遮罩。
 */
class MaskingPromptPathTest extends TestCase
{
    use MaskingCases;

    private function promptFor(string $text): string
    {
        $captured = '';
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generate')->once()->andReturnUsing(function (string $prompt) use (&$captured): array {
            $captured = $prompt;

            return ['candidates' => [['content' => ['parts' => [['text' => '{"is_expense":true,"currency":"TWD","amount":85,"merchant":"某某咖啡","date":null}']]]]]];
        });

        (new AiNotificationParser($gemini))->parse('某某咖啡 消費 NT$85 '.$text, CarbonImmutable::parse('2026-11-15 13:00:00', 'Asia/Taipei'));

        return $captured;
    }

    /**
     * @param  array<int, string>  $leaks
     */
    #[DataProvider('leakCases')]
    public function test_prompt_has_no_sensitive_numbers(string $text, array $leaks): void
    {
        $prompt = $this->promptFor($text);

        foreach ($leaks as $leak) {
            $this->assertStringNotContainsString($leak, $prompt);
        }
        $this->assertStringContainsString('****', $prompt);
    }

    #[DataProvider('keptCases')]
    public function test_prompt_keeps_amounts_dates_and_references(string $text): void
    {
        $this->assertStringContainsString(NotificationTextMasker::normalizeWidth($text), $this->promptFor($text));
    }

    #[DataProvider('leakCases')]
    public function test_mask_removes_sensitive_numbers(string $text, array $leaks): void
    {
        $masked = NotificationTextMasker::mask($text);

        foreach ($leaks as $leak) {
            $this->assertStringNotContainsString($leak, $masked);
        }
    }

    #[DataProvider('keptCases')]
    public function test_mask_keeps_text(string $text): void
    {
        $this->assertSame($text, NotificationTextMasker::mask($text));
    }
}
