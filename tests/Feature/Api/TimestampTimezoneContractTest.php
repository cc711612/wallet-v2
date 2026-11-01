<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * 時間戳契約：app.timezone 為台灣時間，DB 存台灣時間，API 輸出不帶時區的 "Y-m-d H:i:s"。
 * 裝置 expired_at 由前端以 UTC（不帶時區字串）送入，寫入前需轉成 app.timezone。
 */
class TimestampTimezoneContractTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_app_timezone_is_taipei(): void
    {
        $this->assertSame('Asia/Taipei', config('app.timezone'));
    }

    public function test_detail_and_wallet_list_timestamps_are_taipei_local_strings(): void
    {
        // UTC 2026-10-31 16:30:00 = 台灣 2026-11-01 00:30:00
        $this->travelTo(Carbon::parse('2026-10-31 16:30:00', 'UTC'));

        $alice = $this->createUser(1, 'alice');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);
        $jwt = $this->userJwt($alice);

        $this->withToken($jwt)->postJson('/api/wallet/1/detail', [
            'wallet_user_id' => 10,
            'type' => 2,
            'symbol_operation_type_id' => 2,
            'title' => 'Dinner',
            'value' => 300,
            'select_all' => false,
            'users' => [10],
            'splits' => [['user_id' => 10, 'value' => 300]],
        ])->assertOk();

        $detail = $this->withToken($jwt)->getJson('/api/wallet/1/detail')->assertOk();
        $this->assertSame('2026-11-01 00:30:00', $detail->json('data.wallet.details.0.created_at'), (string) $detail->getContent());

        $list = $this->withToken($jwt)->getJson('/api/wallet')->assertOk();
        $this->assertSame('2026-11-01 00:30:00', $list->json('data.wallets.0.updated_at'), (string) $list->getContent());
    }

    public function test_device_expired_at_is_converted_from_utc_to_app_timezone(): void
    {
        $alice = $this->createUser(1, 'alice');
        $this->createWallet(1, 1);
        $this->createWalletUser(10, 1, 1, true);

        $this->withToken($this->userJwt($alice))->postJson('/api/device', [
            'platform' => 'ios',
            'device_name' => 'iphone',
            'device_type' => 'mobile',
            'fcm_token' => 'fcm-tz',
            'expired_at' => '2026-11-01 00:00:00',
        ])->assertOk();

        $this->assertSame('2026-11-01 08:00:00', DB::table('devices')->where('fcm_token', 'fcm-tz')->value('expired_at'));
    }
}
