<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Services\WalletDetailIngestService;
use App\Jobs\NotificationFCM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\Concerns\ChecksIngestTemplateFixtures;
use Tests\TestCase;

/**
 * POST /api/wallet/{wallet}/detail/ingest 的預設模式：模板白名單（不覆蓋任何 ingest 設定）。
 */
class WalletDetailIngestTemplateTest extends TestCase
{
    use BuildsAuthFixtures;
    use ChecksIngestTemplateFixtures;
    use RefreshDatabase;

    private const RECEIVED_AT = '2026-11-15T13:00:00+08:00';

    private const TEXT = '【範例銀行】您的信用卡於11/14 19:20在鼎泰豐 信義店消費NT$1,350。';

    /** @var array<string, mixed> */
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([]);
        Http::preventStrayRequests();

        $this->alice = $this->createUser(1, 'alice');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
    }

    private function useExampleTemplate(): void
    {
        config(['ingest_templates.templates' => [self::exampleTemplate()]]);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function ingest(array $override = []): TestResponse
    {
        return $this->withToken($this->userJwt($this->alice))
            ->postJson('/api/wallet/1/detail/ingest', array_merge([
                'text' => self::TEXT,
                'received_at' => self::RECEIVED_AT,
                'source' => 'com.example.bank',
            ], $override));
    }

    private function dedupKey(string $text): string
    {
        $service = app(WalletDetailIngestService::class);

        return $service->dedupKey(1, 10, $text, $service->receivedAt(self::RECEIVED_AT));
    }

    private function assertRejected(TestResponse $response, string $text): void
    {
        $response->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey($text)));
        Http::assertNothingSent();
    }

    public function test_defaults_are_templates_only(): void
    {
        // 預設只有內建模板（目前國泰、玉山兩個），啟發式與 AI 都關閉
        $this->assertSame(['cathay-credit-purchase', 'esun-credit-purchase'], array_column((array) config('ingest_templates.templates'), 'name'));
        $this->assertFalse(config('services.ingest.heuristic_enabled'));
        $this->assertFalse(config('services.ingest.ai_enabled'));
    }

    /**
     * 框架本身：模板清單為空時，任何原文（包含國泰真實樣本）都 400。
     *
     * @return array<string, array{0:string}>
     */
    public static function anyTextIncludingBuiltInFormat(): array
    {
        return array_merge(self::anyText(), [
            '玉山真實樣本' => ['信用卡消費600元通知'],
            '國泰真實樣本' => ["【刷卡通知】金額NT$600元 \n卡號末四碼1234於 2026/11/15 20:52在樂購蝦皮股份有限公司 刷卡。立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細"],
        ]);
    }

    #[DataProvider('anyTextIncludingBuiltInFormat')]
    public function test_empty_template_config_rejects_everything(string $text): void
    {
        config(['ingest_templates.templates' => []]);

        $this->assertRejected($this->ingest(['text' => $text]), $text);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function anyText(): array
    {
        return [
            '範例模板格式' => [self::TEXT],
            '啟發式規則可解析的玉山寫法' => ['【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。'],
            '7-ELEVEN' => ['您於7-ELEVEN消費 NT$85'],
            '非消費' => ['您於全家消費NT$100之交易已取消'],
        ];
    }

    /** 預設 config（含內建模板）下，不命中任何內建模板的原文仍 400 */
    #[DataProvider('anyText')]
    public function test_default_config_rejects_everything_without_writing(string $text): void
    {
        $this->assertRejected($this->ingest(['text' => $text]), $text);
    }

    public function test_template_match_creates_detail(): void
    {
        $this->useExampleTemplate();

        $response = $this->ingest();

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', '鼎泰豐 信義店')
            ->assertJsonPath('data.value', 1350)
            ->assertJsonPath('data.date', '2026-11-14')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.parser', 'template:example-bank-purchase')
            ->assertJsonPath('data.duplicate', false);

        $detail = DB::table('wallet_details')->first();
        $this->assertSame((int) $detail->id, $response->json('data.id'));
        $this->assertSame(1, (int) $detail->is_personal);
        $this->assertSame(2, (int) $detail->type);
        $this->assertSame('[auto-ingest] '.self::TEXT, $detail->note);
        Queue::assertNotPushed(NotificationFCM::class);
        Http::assertNothingSent();

        // 重送同一則：回既有那筆
        $this->ingest()->assertJsonPath('data.duplicate', true)->assertJsonPath('data.id', (int) $detail->id);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_no_template_match_returns_400(): void
    {
        $this->useExampleTemplate();
        $text = '您於全家便利商店消費NT$85';

        $this->assertRejected($this->ingest(['text' => $text]), $text);
    }

    public function test_extra_trailing_sentence_fails_full_match(): void
    {
        $this->useExampleTemplate();
        $text = self::TEXT.'如有疑問請洽客服。';

        $this->assertRejected($this->ingest(['text' => $text]), $text);
    }

    public function test_source_mismatch_does_not_match(): void
    {
        $this->useExampleTemplate();

        $this->assertRejected($this->ingest(['source' => 'com.other.bank']), self::TEXT);
        $this->assertRejected($this->ingest(['source' => null]), self::TEXT);
    }

    public function test_heuristic_and_ai_are_not_used_by_default_even_if_text_is_parsable(): void
    {
        $this->useExampleTemplate();
        $text = '【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。';

        $this->assertRejected($this->ingest(['text' => $text, 'source' => 'com.esunbank']), $text);
    }

    public function test_invalid_template_config_returns_generic_500_without_writing(): void
    {
        config(['ingest_templates.templates' => [array_merge(self::exampleTemplate(), ['currency' => 'USD'])]]);

        $this->ingest()
            ->assertStatus(500)
            ->assertJsonPath('message', '系統錯誤，請稍後再試');
        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function lintFailingPatterns(): array
    {
        return [
            '.* 吞尾句' => ['SECRETPATTERN(?<merchant>[^\n]{1,10})消費NT\\$(?<amount>\\d+(?:\\.\\d{1,2})?).*'],
            '(*ACCEPT)' => ['SECRETPATTERN(?<merchant>[^\n]{1,10})消費NT\\$(?<amount>\\d+(?:\\.\\d{1,2})?)(*ACCEPT)'],
            '括號不平衡' => ['x)|SECRETPATTERN(?<merchant>[^\n]{1,10})消費NT\\$(?<amount>\\d+(?:\\.\\d{1,2})?)|(?:y'],
            '(?s)' => ['(?s)SECRETPATTERN(?<merchant>[^\n]{1,10})消費NT\\$(?<amount>\\d+(?:\\.\\d{1,2})?)'],
        ];
    }

    #[DataProvider('lintFailingPatterns')]
    public function test_lint_failing_template_returns_500_without_leaking_pattern(string $pattern): void
    {
        config(['ingest_templates.templates' => [['name' => 'fixture-bad', 'pattern' => $pattern]]]);

        $response = $this->ingest(['text' => 'SECRETPATTERN全家消費NT$85']);

        $response->assertStatus(500)->assertJsonPath('message', '系統錯誤，請稍後再試');
        $this->assertStringNotContainsString('SECRETPATTERN', (string) $response->getContent());
        $this->assertStringNotContainsString('merchant', (string) $response->getContent());
        $this->assertSame(0, DB::table('wallet_details')->count());
    }
}
