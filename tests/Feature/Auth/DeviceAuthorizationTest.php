<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-5：裝置只能刪自己的；update 不能指定 wallet_user_id 把 FCM token 掛到別人名下。
 */
class DeviceAuthorizationTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->createUser(1, 'alice');
        $this->createUser(2, 'bob');
        $this->createWallet(1, 1);
        $this->createWallet(2, 2);
        $this->createWalletUser(10, 1, 1, true);
        $this->createWalletUser(20, 2, 2, true);

        DB::table('devices')->insert([
            ['id' => 1, 'wallet_user_id' => 20, 'fcm_token' => 'bob-fcm', 'platform' => 'ios', 'device_name' => 'b', 'device_type' => 'mobile'],
            ['id' => 2, 'wallet_user_id' => 10, 'fcm_token' => 'alice-fcm', 'platform' => 'ios', 'device_name' => 'a', 'device_type' => 'mobile'],
        ]);
    }

    public function test_cannot_delete_other_members_device(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->deleteJson('/api/device/1')
            ->assertStatus(403);

        $this->assertNull(DB::table('devices')->where('id', 1)->value('deleted_at'));
    }

    public function test_can_delete_own_device(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->deleteJson('/api/device/2')
            ->assertOk();

        $this->assertNotNull(DB::table('devices')->where('id', 2)->value('deleted_at'));
    }

    public function test_update_ignores_client_wallet_user_id(): void
    {
        $this->withToken($this->userJwt($this->alice))
            ->putJson('/api/device/99', [
                'wallet_user_id' => 20,
                'platform' => 'ios',
                'device_name' => 'evil',
                'device_type' => 'mobile',
                'fcm_token' => 'attacker-fcm',
            ])
            ->assertOk();

        $this->assertSame(0, DB::table('devices')->where('fcm_token', 'attacker-fcm')->where('wallet_user_id', 20)->count());
        $this->assertSame(1, DB::table('devices')->where('fcm_token', 'attacker-fcm')->where('wallet_user_id', 10)->count());
    }
}
