<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Services\WalletDetailIngestService;
use App\Jobs\NotificationFCM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * POST /api/wallet/{wallet}/detail/ingest：銀行刷卡推播自動記帳。
 */
class WalletDetailIngestTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    private const RECEIVED_AT = '2026-11-15T13:15:00+08:00';

    private const RULE_TEXT = '【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。';

    private const AI_TEXT = 'EasyCard 自動加值 一百二十元 @ 某某咖啡 卡號末四碼 5678';

    /** @var array<string, mixed> */
    private array $alice;

    /** @var array<string, mixed> */
    private array $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        // 只開啟紀錄、不註冊預設回應；未被個別測試 fake 的對外請求一律丟例外
        Http::fake([]);
        Http::preventStrayRequests();
        config([
            'services.ingest.ai_enabled' => false,
            'services.ingest.lock_wait_seconds' => 0,
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.retry_delay_ms' => 0,
        ]);

        $this->alice = $this->createUser(1, 'alice');
        $this->bob = $this->createUser(2, 'bob');
        $this->createWallet(1, 1);
        $this->createWallet(2, 2);
        $this->createWalletUser(10, 1, 1, true);
        $this->createWalletUser(11, 1, 2, false);
        $this->createWalletUser(20, 2, 2, true);
        // 其他成員有開推播：若明細不是個人消費，就會派送 NotificationFCM
        DB::table('wallet_users')->update(['notify_enable' => 1]);
    }

    /**
     * @param  array<string, mixed>  $override
     * @param  array<string, mixed>|null  $user
     */
    private function ingest(array $override = [], int $walletId = 1, ?array $user = null): TestResponse
    {
        return $this->withToken($this->userJwt($user ?? $this->alice))
            ->postJson("/api/wallet/{$walletId}/detail/ingest", array_merge([
                'text' => self::RULE_TEXT,
                'received_at' => self::RECEIVED_AT,
                'source' => 'com.esunbank',
            ], $override));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function geminiReturns(array|string $payload): void
    {
        $text = is_string($payload) ? $payload : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]])]);
    }

    private function dedupKey(string $text = self::RULE_TEXT, int $walletUserId = 10): string
    {
        $service = app(WalletDetailIngestService::class);

        return $service->dedupKey(1, $walletUserId, $text, $service->receivedAt(self::RECEIVED_AT));
    }

    public function test_rule_parsed_notification_creates_personal_expense_without_calling_ai(): void
    {
        config(['services.ingest.ai_enabled' => true]);

        $response = $this->ingest();

        $detail = DB::table('wallet_details')->first();
        $this->assertNotNull($detail);
        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', '')
            ->assertJsonPath('data.id', (int) $detail->id)
            ->assertJsonPath('data.title', '全家便利商店')
            ->assertJsonPath('data.value', 1234)
            ->assertJsonPath('data.date', '2026-11-15')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.parser', 'rule')
            ->assertJsonPath('data.duplicate', false);

        Http::assertNothingSent();
    }

    public function test_written_fields_are_fixed_personal_expense_and_no_fcm_is_dispatched(): void
    {
        $this->ingest([
            // client 嘗試覆寫的欄位一律忽略
            'is_personal' => false,
            'select_all' => true,
            'type' => 1,
            'symbol_operation_type_id' => 1,
            'users' => [10, 11],
            'category_id' => 3,
            'wallet_user_id' => 11,
        ])->assertJsonPath('status', true);

        $this->assertSame(1, DB::table('wallet_details')->count());
        $detail = DB::table('wallet_details')->first();
        $this->assertSame(2, (int) $detail->type);
        $this->assertSame(2, (int) $detail->symbol_operation_type_id);
        $this->assertSame(0, (int) $detail->select_all);
        $this->assertSame(1, (int) $detail->is_personal);
        $this->assertSame(10, (int) $detail->payment_wallet_user_id);
        $this->assertSame(10, (int) $detail->created_by);
        $this->assertNull($detail->category_id);
        $this->assertSame('全家便利商店', $detail->title);
        $this->assertEquals(1234, $detail->value);
        $this->assertSame('2026-11-15', substr((string) $detail->date, 0, 10));
        $this->assertStringStartsWith('[auto-ingest] ', (string) $detail->note);
        $this->assertStringContainsString('末四碼****的信用卡', (string) $detail->note);
        $this->assertStringNotContainsString('5678', (string) $detail->note);
        $this->assertSame([10], DB::table('wallet_detail_wallet_user')->where('wallet_detail_id', $detail->id)->pluck('wallet_user_id')->map(static fn ($id): int => (int) $id)->all());

        Queue::assertNotPushed(NotificationFCM::class);
    }

    public function test_control_non_personal_store_does_dispatch_fcm(): void
    {
        // 對照組：證明 Queue::fake 能攔到一般明細的推播，上一個測試的 assertNotPushed 才有意義
        $this->withToken($this->userJwt($this->alice))->postJson('/api/wallet/1/detail', [
            'type' => 2, 'symbol_operation_type_id' => 2, 'title' => 'Dinner', 'value' => 100,
            'select_all' => false, 'users' => [10, 11],
        ])->assertJsonPath('status', true);

        Queue::assertPushed(NotificationFCM::class);
    }

    public function test_note_is_truncated_to_1000_characters_of_text(): void
    {
        $text = self::RULE_TEXT.str_repeat('備', 1990 - mb_strlen(self::RULE_TEXT));

        $this->ingest(['text' => $text])->assertJsonPath('status', true);

        $note = (string) DB::table('wallet_details')->value('note');
        $this->assertSame(mb_strlen('[auto-ingest] ') + 1000, mb_strlen($note));
    }

    public function test_date_in_text_is_used(): void
    {
        $this->ingest(['text' => '國泰世華：2026/11/13 您於全聯福利中心消費 NT$2,345.5'])
            ->assertJsonPath('data.date', '2026-11-13')
            ->assertJsonPath('data.value', 2345.5);
    }

    public function test_received_at_is_converted_to_taipei_date(): void
    {
        // UTC 16:30 = 台北隔天 00:30
        $this->ingest(['received_at' => '2026-11-14T16:30:00Z'])->assertJsonPath('data.date', '2026-11-15');
    }

    public function test_duplicate_returns_existing_detail_and_does_not_create_again(): void
    {
        $first = $this->ingest()->assertJsonPath('data.duplicate', false);
        $id = (int) $first->json('data.id');

        // 同一則推播：原文前後空白不同、received_at 同一分鐘不同秒數與時區寫法
        $second = $this->ingest(['text' => '  '.self::RULE_TEXT."\n", 'received_at' => '2026-11-15T05:15:42Z']);

        $second->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.title', '全家便利商店')
            ->assertJsonPath('data.date', '2026-11-15');
        $this->assertSame(1, DB::table('wallet_details')->count());
        $this->assertSame($id, Cache::get($this->dedupKey()));
        Queue::assertNotPushed(NotificationFCM::class);
    }

    public function test_different_minute_is_not_duplicate(): void
    {
        $this->ingest();
        $this->ingest(['received_at' => '2026-11-15T13:16:00+08:00'])->assertJsonPath('data.duplicate', false);

        $this->assertSame(2, DB::table('wallet_details')->count());
    }

    public function test_existing_dedup_key_prevents_creation(): void
    {
        // 模擬併發的另一個請求已建立完成、寫入去重 key
        Cache::add($this->dedupKey(), 987, now()->addDay());

        $this->ingest()->assertJsonPath('data.id', 987)->assertJsonPath('data.duplicate', true);

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_concurrent_request_holding_lock_blocks_second_creation(): void
    {
        // 模擬另一個請求正持有同一則推播的鎖（尚未完成）
        $lock = Cache::lock($this->dedupKey().':lock', 30);
        $this->assertTrue($lock->get());

        $this->ingest()
            ->assertStatus(500)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', '相同推播正在處理中，請稍後再試');
        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey()));

        $lock->release();
        $this->ingest()->assertJsonPath('data.duplicate', false);
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_key_written_while_waiting_is_rechecked_inside_lock(): void
    {
        config(['services.ingest.ai_enabled' => true]);
        $key = $this->dedupKey(self::AI_TEXT);

        // AI 解析期間，併發的另一個請求完成並寫入 key；拿到鎖後必須重新檢查而不再建立
        Http::fake(function () use ($key) {
            Cache::put($key, 555, now()->addDay());

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"is_expense":true,"amount":120,"merchant":"某某咖啡","date":null}']]]]]]);
        });

        $this->ingest(['text' => self::AI_TEXT])->assertJsonPath('data.id', 555)->assertJsonPath('data.duplicate', true);

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_unparseable_text_with_ai_disabled_returns_400_without_writing(): void
    {
        $this->ingest(['text' => self::AI_TEXT])
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey(self::AI_TEXT)));
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function nonExpenseNotifications(): array
    {
        return [
            '回饋入帳' => ['消費獲得回饋 NT$30 已入帳'],
            '繳款' => ['繳款 NT$12,345，商店：信用卡中心'],
            '取消' => ['您在全家刷卡 NT$120 交易已取消'],
            '授權失敗' => ['於全家消費金額NT$120 授權失敗'],
            '交易失敗' => ['您在全家刷卡 NT$120 交易失敗'],
            '未成功' => ['您在全家刷卡 NT$120 未成功'],
            '退刷' => ['您在全家退刷 NT$120'],
            '退款' => ['您在全家消費 NT$120 已退款'],
            '沖銷' => ['於全家消費 NT$120 已沖銷'],
            '撤銷' => ['於全家消費 NT$120 已撤銷'],
            '驗證碼' => ['在 Apple 使用 NT$1,000，驗證碼 123456'],
            '只有累計與額度金額' => ['本期累計 NT$50,000，可用額度 NT$80,000，於全家消費'],
        ];
    }

    #[DataProvider('nonExpenseNotifications')]
    public function test_non_expense_notifications_return_400_without_writing(string $text): void
    {
        $this->ingest(['text' => $text])
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey($text)));
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:int}>
     */
    public static function merchantTruncationCases(): array
    {
        return [
            '商店後接消費金額與卡號' => ['商店：全家 消費金額 1500 元 卡號末四碼 1234', '全家', 1500],
            '商店後接數字+元' => ['商店：全家 1,234元', '全家', 1234],
            '半形冒號' => ['商店:全家 金額:NT$100', '全家', 100],
            '累計金額在前' => ['本期累計消費金額 NT$50,000，本筆刷卡金額 NT$1,200，於全家消費', '全家', 1200],
        ];
    }

    #[DataProvider('merchantTruncationCases')]
    public function test_merchant_is_truncated_and_title_has_no_card_digits(string $text, string $title, int $value): void
    {
        $this->ingest(['text' => $text])
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.value', $value);

        $detail = DB::table('wallet_details')->first();
        $this->assertSame($title, $detail->title);
        $this->assertStringNotContainsString('1234 ', (string) $detail->note.' ');
    }

    public function test_title_is_masked_before_writing(): void
    {
        // 截斷規則沒擋到的號碼（例如商店名中夾帶長串帳號）寫入前也要遮罩
        $this->ingest(['text' => '您於 ABC 1234567890123 商行消費 NT$80'])
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'ABC **** 商行');

        $this->assertSame('ABC **** 商行', (string) DB::table('wallet_details')->value('title'));
    }

    public function test_ai_fallback_creates_detail_with_masked_prompt(): void
    {
        config(['services.ingest.ai_enabled' => true]);
        $this->geminiReturns(['is_expense' => true, 'amount' => 120, 'merchant' => '某某咖啡', 'date' => '2026-11-14']);

        $response = $this->ingest(['text' => self::AI_TEXT]);

        $response->assertJsonPath('status', true)
            ->assertJsonPath('data.parser', 'ai')
            ->assertJsonPath('data.title', '某某咖啡')
            ->assertJsonPath('data.value', 120)
            ->assertJsonPath('data.date', '2026-11-14')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.duplicate', false);
        $this->assertSame(1, DB::table('wallet_details')->count());
        $this->assertSame(1, (int) DB::table('wallet_details')->value('is_personal'));

        Http::assertSentCount(1);
        Http::assertSent(static function (HttpRequest $request): bool {
            $body = $request->body();

            return ! str_contains($body, '5678') && str_contains($body, '****');
        });
    }

    public function test_ai_date_too_far_from_received_at_falls_back_to_received_date(): void
    {
        config(['services.ingest.ai_enabled' => true]);
        $this->geminiReturns(['is_expense' => true, 'amount' => 120, 'merchant' => '某某咖啡', 'date' => '2025-01-01']);

        $this->ingest(['text' => self::AI_TEXT])->assertJsonPath('data.date', '2026-11-15');
    }

    /**
     * @return array<string, array{0:array<string, mixed>|string}>
     */
    public static function invalidAiOutputs(): array
    {
        return [
            '負金額' => [['is_expense' => true, 'amount' => -120, 'merchant' => '某某咖啡', 'date' => null]],
            '超過上限' => [['is_expense' => true, 'amount' => 1000001, 'merchant' => '某某咖啡', 'date' => null]],
            'is_expense=false' => [['is_expense' => false, 'amount' => 120, 'merchant' => '某某咖啡', 'date' => null]],
            '壞 JSON' => ['{"is_expense": true, "amount": 12'],
            '缺欄位' => [['is_expense' => true, 'amount' => 120]],
        ];
    }

    /**
     * @param  array<string, mixed>|string  $output
     */
    #[DataProvider('invalidAiOutputs')]
    public function test_invalid_ai_output_returns_400_without_writing(array|string $output): void
    {
        config(['services.ingest.ai_enabled' => true]);
        $this->geminiReturns($output);

        $this->ingest(['text' => self::AI_TEXT])
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '無法解析推播內容');

        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey(self::AI_TEXT)));
    }

    public function test_ai_failure_does_not_write_or_occupy_dedup_key(): void
    {
        config(['services.ingest.ai_enabled' => true]);
        Http::fakeSequence()
            ->push(['error' => ['message' => 'upstream exploded with secret detail']], 500)
            ->push(['candidates' => [['content' => ['parts' => [['text' => '{"is_expense":true,"amount":120,"merchant":"某某咖啡","date":null}']]]]]]);

        $this->ingest(['text' => self::AI_TEXT])
            ->assertStatus(500)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 500)
            ->assertJsonPath('message', 'AI 服務暫時無法使用，請稍後再試');
        $this->assertSame(0, DB::table('wallet_details')->count());
        $this->assertNull(Cache::get($this->dedupKey(self::AI_TEXT)));

        // 重送同一則：key 沒被佔用，可以正常建立
        $this->ingest(['text' => self::AI_TEXT])->assertJsonPath('data.duplicate', false)->assertJsonPath('data.parser', 'ai');
        $this->assertSame(1, DB::table('wallet_details')->count());
    }

    public function test_other_users_wallet_is_forbidden(): void
    {
        $this->ingest([], 2)->assertStatus(403)->assertJsonPath('message', '無此帳本權限');

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_missing_token_is_unauthorized(): void
    {
        $this->postJson('/api/wallet/1/detail/ingest', ['text' => self::RULE_TEXT, 'received_at' => self::RECEIVED_AT])->assertStatus(401);
    }

    public function test_validation_errors(): void
    {
        $this->ingest(['text' => ''])->assertStatus(422)->assertJsonValidationErrors('text');
        $this->ingest(['text' => str_repeat('a', 2001)])->assertStatus(422)->assertJsonValidationErrors('text');
        $this->ingest(['received_at' => 'not-a-date'])->assertStatus(422)->assertJsonValidationErrors('received_at');
        $this->ingest(['source' => str_repeat('s', 101)])->assertStatus(422)->assertJsonValidationErrors('source');

        $this->assertSame(0, DB::table('wallet_details')->count());
    }

    public function test_route_is_not_swallowed_by_detail_show(): void
    {
        $this->assertSame('api.wallet.detail.ingest', app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/api/wallet/1/detail/ingest', 'POST')
        )->getName());
    }

    public function test_ingest_is_throttled_per_wallet_user(): void
    {
        for ($i = 0; $i < 20; $i++) {
            // client 自帶 wallet_user 不能用來換 key 繞過限流（middleware 會覆寫）
            $this->ingest(['text' => '您在全家刷卡 NT$'.($i + 1), 'wallet_user' => [1 => ['id' => 1000 + $i]]])->assertOk();
        }

        $this->ingest(['text' => '您在全家刷卡 NT$999'])->assertStatus(429);
        $this->assertSame(20, DB::table('wallet_details')->count());

        // 另一位成員（bob 在帳本 1 的 wallet_user 11）不受影響
        $this->ingest(['text' => '您在全家刷卡 NT$999'], 1, $this->bob)->assertOk()->assertJsonPath('data.duplicate', false);
    }
}
