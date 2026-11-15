<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Ingest\ExpenseTextScreen;
use App\Domain\Wallet\Ingest\NotificationTextMasker;
use App\Jobs\NotificationFCM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 內建模板 cathay-credit-purchase：用預設 config（不覆蓋任何 ingest 設定）端到端呼叫。
 * 樣本來自 tests/Fixtures/ingest/cathay-credit-purchase.json（真實樣本卡號末四碼已換成 1234）。
 */
class WalletDetailIngestCathayTemplateTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const REAL = "【刷卡通知】金額NT$600元 \n卡號末四碼1234於 2026/11/15 20:52在樂購蝦皮股份有限公司 刷卡。立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細";

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

    private function ingest(string $text, ?string $source = '國泰世華'): TestResponse
    {
        return $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail/ingest', [
            'text' => $text,
            'received_at' => '2026-11-15T20:53:10+08:00',
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
            'title + 換行 + text' => ["國泰世華銀行\n".self::REAL],
            'title + 空白 + text' => ['國泰世華銀行 '.self::REAL],
            '尾端多空白與換行' => [self::REAL."  \n"],
        ];
    }

    #[DataProvider('realCompositions')]
    public function test_real_sample_is_recorded_with_default_config(string $text): void
    {
        $this->assertFalse(config('services.ingest.heuristic_enabled'));
        $this->assertFalse(config('services.ingest.ai_enabled'));

        $response = $this->ingest($text);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', '樂購蝦皮股份有限公司')
            ->assertJsonPath('data.value', 600)
            ->assertJsonPath('data.date', '2026-11-15')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.parser', 'template:cathay-credit-purchase')
            ->assertJsonPath('data.duplicate', false);

        $detail = DB::table('wallet_details')->first();
        $this->assertSame(1, DB::table('wallet_details')->count());
        $this->assertEquals(600, $detail->value);
        $this->assertSame('樂購蝦皮股份有限公司', $detail->title);
        $this->assertSame('2026-11-15', substr((string) $detail->date, 0, 10));
        $this->assertSame(1, (int) $detail->is_personal);
        $this->assertSame(0, (int) $detail->select_all);
        $this->assertSame(2, (int) $detail->type);
        $this->assertSame(10, (int) $detail->payment_wallet_user_id);
        $this->assertStringStartsWith('[auto-ingest] ', (string) $detail->note);
        $this->assertStringContainsString('卡號末四碼****於', (string) $detail->note);
        $this->assertStringNotContainsString('1234', (string) $detail->note);
        Queue::assertNotPushed(NotificationFCM::class);
        Http::assertNothingSent();

        // 重送同一則：回既有那筆
        $this->ingest($text)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.id', (int) $detail->id);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_source_is_not_restricted_yet(): void
    {
        $this->ingest(self::REAL, null)->assertJsonPath('status', true);
        $this->ingest(self::REAL.' ', 'com.cathaybk.mobile')->assertJsonPath('data.duplicate', true);
    }

    public function test_synthetic_positive_sample_is_recorded(): void
    {
        $text = "【刷卡通知】金額NT$1,280元 \n卡號末四碼1234於 2026/11/14 12:05在統一超商 刷卡。立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細";

        $this->ingest($text)
            ->assertJsonPath('data.value', 1280)
            ->assertJsonPath('data.title', '統一超商')
            ->assertJsonPath('data.date', '2026-11-14');
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function negativeSamples(): array
    {
        $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/ingest/cathay-credit-purchase.json'), true);

        $cases = [];
        foreach ((array) $fixture['negative'] as $sample) {
            $cases[(string) $sample['reason']] = [(string) $sample['text']];
        }

        return $cases;
    }

    #[DataProvider('negativeSamples')]
    public function test_negative_samples_are_rejected(string $text): void
    {
        $this->ingest($text)
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        Http::assertNothingSent();
    }

    public function test_fixed_marketing_tail_is_not_blocked_by_second_line_screen(): void
    {
        // 固定尾句含「折抵」「消費金額」：這些屬於軟排除，模板二次檢查只用強排除與外幣，因此不會誤殺
        $screen = new ExpenseTextScreen;
        $normalized = trim(NotificationTextMasker::normalizeWidth(NotificationTextMasker::sanitize(self::REAL)));

        $this->assertFalse($screen->rejects($normalized));
        // 同一段尾句之外若出現強排除字或外幣，仍然擋下
        $this->assertTrue($screen->rejects(str_replace('樂購蝦皮股份有限公司', '樂購蝦皮股份有限公司 交易未成功', $normalized)));
        $this->assertTrue($screen->rejects(str_replace('樂購蝦皮股份有限公司', 'AMAZON USD', $normalized)));
    }
}
