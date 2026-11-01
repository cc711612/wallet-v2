<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\NotificationFCM;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-18：指令改讀 config('wallet.auto_wallet.*')，config:cache 後設定仍然有效。
 */
class AutoWalletCommandConfigTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_auto_calculate_uses_configured_user_id(): void
    {
        Http::fake();
        config(['wallet.auto_wallet.user_id' => 99]);
        $this->createUser(99, 'auto');
        DB::table('wallets')->insert([
            'id' => 1, 'user_id' => 99, 'code' => 'AUTO0001', 'title' => now((string) config('app.business_timezone'))->startOfMonth()->subMonth()->format('Y.m'), 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('command:auto_calculate_wallet')
            ->doesntExpectOutputToContain('No wallet found')
            ->assertSuccessful();
    }

    public function test_auto_create_uses_configured_user_id(): void
    {
        Bus::fake();
        config(['wallet.auto_wallet.user_id' => 99]);

        $this->artisan('command:auto_create_wallet')
            ->expectsOutputToContain('User 99 not found')
            ->assertSuccessful();
    }

    public function test_auto_calculate_uses_business_timezone_month_at_taipei_midnight(): void
    {
        Bus::fake();
        // 台灣 2026-11-01 00:00 = UTC 2026-10-31 16:00：結算 2026.10，推播內文與批次日期用台灣時間
        $this->travelTo(Carbon::parse('2026-10-31 16:00:00', 'UTC'));
        config(['wallet.auto_wallet.user_id' => 99, 'app.business_timezone' => 'Asia/Taipei']);
        $this->createUser(99, 'auto');
        DB::table('wallets')->insert([
            'id' => 1, 'user_id' => 99, 'code' => 'AUTO0001', 'title' => '2026.10', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wallet_users')->insert([
            'id' => 10, 'wallet_id' => 1, 'user_id' => 99, 'name' => 'auto', 'token' => 'tok', 'notify_enable' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('command:auto_calculate_wallet')
            ->doesntExpectOutputToContain('No wallet found')
            ->assertSuccessful();

        Bus::assertDispatched(NotificationFCM::class, function (NotificationFCM $job): bool {
            $props = (fn (): array => get_object_vars($this))->call($job);
            $values = implode('|', array_map(fn ($v): string => is_scalar($v) ? (string) $v : '', $props));

            return str_contains($values, '結算時間: 2026-11-01 00:00:00') && str_contains($values, '-20261101-');
        });
    }

    public function test_auto_create_uses_business_timezone_month_at_taipei_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 16:00:00', 'UTC'));
        config(['wallet.auto_wallet.user_id' => 99, 'app.business_timezone' => 'Asia/Taipei']);
        $this->createUser(99, 'auto');
        DB::table('wallets')->insert([
            'id' => 1, 'user_id' => 99, 'code' => 'AUTO0001', 'title' => '2026.11', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('command:auto_create_wallet')
            ->expectsOutputToContain('Wallet 2026.11 already exists.')
            ->assertSuccessful();
    }

    public function test_monthly_schedules_run_at_taipei_midnight(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'command:auto_create_wallet'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('0 0 1 * *', $event->expression);
        $this->assertSame('Asia/Taipei', (string) $event->timezone);
    }

    public function test_app_code_does_not_call_env_directly(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/\benv\(/', (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'app/ 內不可直接呼叫 env()，請改讀 config');
    }
}
