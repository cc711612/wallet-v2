<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\NotificationFCM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 內建模板 esun-credit-purchase：用預設 config 端到端呼叫。
 * 樣本來自 tests/Fixtures/ingest/esun-credit-purchase.json（玉山銀行 App 與玉山 Wallet 同一筆消費各推一則相同原文）。
 */
class WalletDetailIngestEsunTemplateTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const REAL = '信用卡消費600元通知';

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
        DB::table('wallet_users')->update(['notify_enable' => 1]);
    }

    private function ingest(string $text, ?string $source = '玉山銀行', string $receivedAt = '2026-11-15T20:53:10+08:00'): TestResponse
    {
        return $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail/ingest', [
            'text' => $text,
            'received_at' => $receivedAt,
            'source' => $source,
        ]);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function realCompositions(): array
    {
        return [
            '只有 text' => [self::REAL],
            'title + 換行 + text' => ["玉山銀行\n".self::REAL],
            'title + 空白 + text' => ['玉山銀行 '.self::REAL],
        ];
    }

    #[DataProvider('realCompositions')]
    public function test_real_sample_is_recorded_with_default_config(string $text): void
    {
        $this->assertFalse(config('services.ingest.heuristic_enabled'));
        $this->assertFalse(config('services.ingest.ai_enabled'));

        // received_at 是 UTC 16:30 → 台北隔天 00:30：日期要用台北日期
        $response = $this->ingest($text, '玉山銀行', '2026-11-15T16:30:00Z');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', '玉山信用卡消費')
            ->assertJsonPath('data.value', 600)
            ->assertJsonPath('data.date', '2026-11-16')
            ->assertJsonPath('data.parser', 'template:esun-credit-purchase')
            ->assertJsonPath('data.duplicate', false);

        $detail = DB::table('wallet_details')->first();
        $this->assertEquals(600, $detail->value);
        $this->assertSame('玉山信用卡消費', $detail->title);
        $this->assertSame('2026-11-16', substr((string) $detail->date, 0, 10));
        $this->assertSame(1, (int) $detail->is_personal);
        $this->assertSame('[auto-ingest] '.str_replace("\n", ' ', $text), $detail->note);
        Queue::assertNotPushed(NotificationFCM::class);
        Http::assertNothingSent();

        $this->ingest($text, '玉山銀行', '2026-11-15T16:30:00Z')->assertJsonPath('data.duplicate', true)->assertJsonPath('data.id', (int) $detail->id);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_full_width_amount_is_recorded(): void
    {
        $this->ingest('信用卡消費６００元通知')->assertJsonPath('status', true)->assertJsonPath('data.value', 600);
    }

    public function test_same_push_from_two_apps_in_the_same_minute_is_recorded_once(): void
    {
        $first = $this->ingest(self::REAL, '玉山銀行', '2026-11-15T20:53:10+08:00');
        $second = $this->ingest(self::REAL, '玉山Wallet', '2026-11-15T20:53:45+08:00');

        $first->assertJsonPath('data.duplicate', false);
        $second->assertJsonPath('status', true)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_same_push_from_two_apps_across_a_minute_boundary_is_recorded_twice(): void
    {
        // 已知限制：去重 key 以分鐘為單位，跨過分鐘邊界的兩則會建兩筆（建議 MacroDroid 只監聽其中一個 App）
        $this->ingest(self::REAL, '玉山銀行', '2026-11-15T20:53:59+08:00')->assertJsonPath('data.duplicate', false);
        $this->ingest(self::REAL, '玉山Wallet', '2026-11-15T20:54:01+08:00')->assertJsonPath('data.duplicate', false);

        $this->assertSame(2, DB::table('wallet_details')->count());
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function negativeSamples(): array
    {
        $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/ingest/esun-credit-purchase.json'), true);

        $cases = [];
        foreach ((array) $fixture['negative'] as $sample) {
            $cases[(string) $sample['reason']] = [(string) $sample['text']];
        }

        return $cases;
    }

    #[DataProvider('negativeSamples')]
    public function test_negative_samples_are_rejected(string $text): void
    {
        Log::spy();

        $this->ingest($text, '玉山Wallet')
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        Http::assertNothingSent();
        // 正常的「不命中」路徑：沒有例外被 report、沒有 preg 錯誤
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_cathay_template_is_unaffected(): void
    {
        $text = "【刷卡通知】金額NT$600元 \n卡號末四碼1234於 2026/11/15 20:52在樂購蝦皮股份有限公司 刷卡。立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細";

        $this->ingest($text, '國泰世華')
            ->assertJsonPath('data.parser', 'template:cathay-credit-purchase')
            ->assertJsonPath('data.title', '樂購蝦皮股份有限公司')
            ->assertJsonPath('data.date', '2026-11-15');
    }

    public function test_template_with_both_merchant_group_and_default_returns_500_without_leaking(): void
    {
        config(['ingest_templates.templates' => [[
            'name' => 'fixture-both',
            'pattern' => 'SECRETPATTERN(?<merchant>[^\n]{1,10})信用卡消費(?<amount>\d+(?:\.\d{1,2})?)元通知',
            'merchant_default' => '玉山信用卡消費',
        ]]]);

        $response = $this->ingest(self::REAL);

        $response->assertStatus(500)->assertJsonPath('message', '系統錯誤，請稍後再試');
        $this->assertStringNotContainsString('SECRETPATTERN', (string) $response->getContent());
        $this->assertSame(0, DB::table('wallet_details')->count());
    }
}
