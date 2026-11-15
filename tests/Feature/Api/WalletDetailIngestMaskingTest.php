<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Ingest\NotificationTextMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\Concerns\MaskingCases;
use Tests\TestCase;

/**
 * 遮罩案例走端點：寫入 DB note 與送給 Gemini 的 request body 兩條路徑都要遮到；不該遮的保留。
 */
class WalletDetailIngestMaskingTest extends TestCase
{
    use BuildsAuthFixtures;
    use MaskingCases;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::preventStrayRequests();
        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.retry_delay_ms' => 0,
        ]);

        $this->alice = $this->createUser(1, 'alice');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
    }

    private function ingest(string $text): TestResponse
    {
        return $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail/ingest', [
            'text' => $text,
            'received_at' => '2026-11-15T13:00:00+08:00',
            'source' => 'com.masking.probe',
        ]);
    }

    /**
     * @param  array<int, string>  $leaks
     */
    #[DataProvider('leakCases')]
    public function test_note_and_gemini_request_are_masked(string $fragment, array $leaks): void
    {
        config(['services.ingest.ai_enabled' => true]);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"is_expense":true,"currency":"TWD","amount":85,"merchant":"某某咖啡","date":null}']]]]]])]);

        $this->ingest('某某咖啡 消費 NT$85 '.$fragment)->assertJsonPath('status', true)->assertJsonPath('data.parser', 'ai');

        $note = (string) DB::table('wallet_details')->value('note');
        $prompts = [];
        Http::assertSent(function (HttpRequest $request) use (&$prompts): bool {
            $prompts[] = (string) data_get(json_decode($request->body(), true), 'contents.0.parts.0.text', '');

            return true;
        });

        $this->assertCount(1, $prompts);
        foreach ($leaks as $leak) {
            $this->assertStringNotContainsString($leak, $note, 'note 漏遮');
            $this->assertStringNotContainsString($leak, $prompts[0], 'Gemini prompt 漏遮');
        }
        $this->assertStringContainsString('****', $note);
        $this->assertStringContainsString('****', $prompts[0]);
    }

    #[DataProvider('keptCases')]
    public function test_note_keeps_amounts_dates_and_references(string $fragment): void
    {
        // 寬鬆但通過 lint 的測試用模板：只為了讓含各種片段的原文寫入，以檢查 note
        config(['ingest_templates.templates' => [[
            'name' => 'fixture-masking-probe',
            'source' => 'com.masking.probe',
            'pattern' => '(?<merchant>[^\s]{1,10})消費NT\$(?<amount>\d+(?:\.\d{1,2})?)(?:\s[\s\S]{1,300})?',
        ]]]);

        $this->ingest('某某咖啡消費NT$85 '.$fragment)->assertJsonPath('status', true);

        $note = (string) DB::table('wallet_details')->value('note');
        $this->assertStringContainsString(NotificationTextMasker::singleLine(NotificationTextMasker::normalizeWidth($fragment)), $note);
        Http::assertNothingSent();
    }
}
