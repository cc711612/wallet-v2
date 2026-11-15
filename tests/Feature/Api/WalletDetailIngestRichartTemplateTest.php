<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Ingest\ExpenseTextScreen;
use App\Domain\Wallet\Ingest\NotificationTemplateRegistry;
use App\Domain\Wallet\Ingest\NotificationTextMasker;
use App\Jobs\NotificationFCM;
use Carbon\CarbonImmutable;
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
 * 內建模板 richart-credit-purchase（台新 Richart 信用卡）：用預設 config 端到端呼叫。
 * 樣本來自 tests/Fixtures/ingest/richart-credit-purchase.json（卡號末四碼已換成 1234）。
 */
class WalletDetailIngestRichartTemplateTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const REAL = '【信用卡消費通知】您的Richart卡(末四碼1234)於11/15-21:05刷卡消費約新臺幣600元，實際消費資訊以帳單為準，如有疑問請洽客服';

    private const REVERSAL = '【信用卡消費通知】您的Richart卡(末四碼1234)於11/15-21:05刷卡消費約新臺幣-600元，實際消費資訊以帳單為準，如有疑問請洽客服';

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

    private function ingest(string $text, ?string $source = 'Richart Life', string $receivedAt = '2026-11-15T21:05:30+08:00'): TestResponse
    {
        return $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail/ingest', [
            'text' => $text,
            'received_at' => $receivedAt,
            'source' => $source,
        ]);
    }

    private function assertRejected(TestResponse $response): void
    {
        $response->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function realCompositions(): array
    {
        return [
            '只有 text' => [self::REAL],
            '空 title + 換行 + text' => ["\n".self::REAL],
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
            ->assertJsonPath('data.title', '台新Richart信用卡消費')
            ->assertJsonPath('data.value', 600)
            ->assertJsonPath('data.date', '2026-11-15')
            ->assertJsonPath('data.parser', 'template:richart-credit-purchase')
            ->assertJsonPath('data.duplicate', false);

        $detail = DB::table('wallet_details')->first();
        $this->assertEquals(600, $detail->value);
        $this->assertSame('台新Richart信用卡消費', $detail->title);
        $this->assertSame('2026-11-15', substr((string) $detail->date, 0, 10));
        $this->assertSame(1, (int) $detail->is_personal);
        $this->assertStringContainsString('(末四碼****)', (string) $detail->note);
        $this->assertStringNotContainsString('1234', (string) $detail->note);
        Queue::assertNotPushed(NotificationFCM::class);
        Http::assertNothingSent();

        $this->ingest($text)->assertJsonPath('data.duplicate', true)->assertJsonPath('data.id', (int) $detail->id);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_synthetic_positive_sample_is_recorded(): void
    {
        $this->ingest(str_replace(['11/15-21:05', '600'], ['11/14-12:05', '1,280'], self::REAL))
            ->assertJsonPath('data.value', 1280)
            ->assertJsonPath('data.date', '2026-11-14');
    }

    public function test_year_is_inferred_across_new_year(): void
    {
        $this->ingest(str_replace('11/15-21:05', '12/31-23:58', self::REAL), 'Richart', '2027-01-01T00:10:00+08:00')
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.date', '2026-12-31');
    }

    public function test_month_day_later_than_received_at_is_rejected(): void
    {
        // 11/15 收到「11/20」：推成 2025-11-20，早於 received_at 超過 31 天 → 拒絕
        $this->assertRejected($this->ingest(str_replace('11/15-21:05', '11/20-10:00', self::REAL)));
        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_same_push_from_two_apps_in_the_same_minute_is_recorded_once(): void
    {
        $first = $this->ingest(self::REAL, 'Richart Life', '2026-11-15T21:05:30+08:00');
        $second = $this->ingest(self::REAL, 'Richart', '2026-11-15T21:05:50+08:00');

        $first->assertJsonPath('data.duplicate', false);
        $second->assertJsonPath('data.duplicate', true)->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_reversal_after_purchase_does_not_touch_the_recorded_purchase(): void
    {
        $purchase = $this->ingest(self::REAL);
        $this->assertRejected($this->ingest(self::REVERSAL));
        $this->assertRejected($this->ingest(self::REVERSAL, 'Richart'));

        $purchase->assertJsonPath('data.duplicate', false);
        $this->assertSame(1, DB::table('wallet_details')->count());
        $this->assertEquals(600, DB::table('wallet_details')->value('value'));
    }

    public function test_reversal_before_purchase_does_not_block_the_purchase(): void
    {
        $this->assertRejected($this->ingest(self::REVERSAL));

        $this->ingest(self::REAL)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.value', 600);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function negativeSamples(): array
    {
        $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/ingest/richart-credit-purchase.json'), true);

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

        $this->assertRejected($this->ingest($text));

        $this->assertSame(0, DB::table('wallet_details')->count());
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_second_line_screen_does_not_reject_fixed_text_but_still_blocks_strong_words(): void
    {
        // 固定文案含「帳單」「客服」「消費資訊」：屬於軟排除或一般字，模板命中後的 screen 只看強排除與外幣，不會誤殺
        $this->assertFalse((new ExpenseTextScreen)->rejects(self::REAL));

        // 用一個有 merchant 群組、同樣帶「帳單」固定尾句的測試模板，證明強排除字混進其他位置仍會被擋
        $registry = NotificationTemplateRegistry::fromArray([[
            'name' => 'fixture-tail-with-billing',
            'pattern' => '在(?<merchant>[^\n，]{1,20})消費約新臺幣(?<amount>\d+(?:\.\d{1,2})?)元，實際消費資訊以帳單為準',
        ]]);
        $at = CarbonImmutable::parse('2026-11-15 21:05:30', 'Asia/Taipei');
        $normalize = static fn (string $t): string => trim(NotificationTextMasker::normalizeWidth(NotificationTextMasker::sanitize($t)));

        $this->assertSame('全家', $registry->match($normalize('在全家消費約新臺幣600元，實際消費資訊以帳單為準'), null, $at)?->merchant);
        $this->assertNull($registry->match($normalize('在全家 交易已取消消費約新臺幣600元，實際消費資訊以帳單為準'), null, $at));
        $this->assertNull($registry->match($normalize('在全家退款消費約新臺幣600元，實際消費資訊以帳單為準'), null, $at));
    }

    public function test_cathay_and_esun_templates_are_unaffected(): void
    {
        $this->ingest("【刷卡通知】金額NT$600元 \n卡號末四碼1234於 2026/11/15 20:52在樂購蝦皮股份有限公司 刷卡。立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細", '國泰世華')
            ->assertJsonPath('data.parser', 'template:cathay-credit-purchase')
            ->assertJsonPath('data.title', '樂購蝦皮股份有限公司');

        $this->ingest('信用卡消費600元通知', '玉山銀行')
            ->assertJsonPath('data.parser', 'template:esun-credit-purchase')
            ->assertJsonPath('data.title', '玉山信用卡消費')
            ->assertJsonPath('data.date', '2026-11-15');

        $this->assertSame(2, DB::table('wallet_details')->count());
    }
}
