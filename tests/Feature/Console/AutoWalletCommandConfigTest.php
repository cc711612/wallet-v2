<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'id' => 1, 'user_id' => 99, 'code' => 'AUTO0001', 'title' => now()->subMonth()->format('Y.m'), 'status' => 1,
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
