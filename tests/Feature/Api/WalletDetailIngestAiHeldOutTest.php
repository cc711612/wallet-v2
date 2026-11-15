<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * AI 備援（實驗性）的 held-out 回歸資料集：第五輪驗收的 87 案（tests/Fixtures/ingest/ai-heldout-round5.php）。
 * 開啟 AI、關閉啟發式、沒有模板；mock Gemini 回傳驗收時的（常常是錯的）結果，確認守衛不寫入。
 */
class WalletDetailIngestAiHeldOutTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::preventStrayRequests();
        config([
            'services.ingest.ai_enabled' => true,
            'services.ingest.heuristic_enabled' => false,
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.retry_delay_ms' => 0,
        ]);

        $this->alice = $this->createUser(1, 'alice');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:array<string, mixed>, 3:int|float|null}>
     */
    public static function heldOut(): array
    {
        /** @var array<string, array{0:string, 1:string, 2:array<string, mixed>, 3:int|float|null}> $cases */
        $cases = require dirname(__DIR__, 2).'/Fixtures/ingest/ai-heldout-round5.php';

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $ai
     */
    #[DataProvider('heldOut')]
    public function test_ai_guard_on_held_out_case(string $group, string $text, array $ai, int|float|null $expected): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => (string) json_encode($ai, JSON_UNESCAPED_UNICODE)]]]]]])]);

        $response = $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail/ingest', [
            'text' => $text,
            'received_at' => '2026-11-15T13:00:00+08:00',
            'source' => 'probe',
        ]);

        if ($expected === null) {
            $response->assertJsonPath('status', false);
            $this->assertSame(0, DB::table('wallet_details')->count(), "[{$group}] 不應寫入：{$text}");

            return;
        }

        $response->assertJsonPath('status', true)->assertJsonPath('data.parser', 'ai');
        $this->assertEquals($expected, DB::table('wallet_details')->value('value'), "[{$group}] {$text}");
    }
}
