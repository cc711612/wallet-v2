<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

/**
 * P0-3：Telescope 預設關閉；非 local 環境只允許白名單 IP；路由要掛 Authorize middleware。
 */
class TelescopeAccessTest extends TestCase
{
    public function test_dashboard_is_not_reachable_when_disabled(): void
    {
        $this->get('/telescope')->assertNotFound();
        $this->getJson('/telescope/telescope-api/requests')->assertNotFound();
    }

    public function test_authorize_middleware_is_configured(): void
    {
        $this->assertContains(Authorize::class, (array) config('telescope.middleware'));
    }

    public function test_enabled_defaults_to_false_in_config_file(): void
    {
        $source = (string) file_get_contents(config_path('telescope.php'));

        $this->assertStringContainsString("env('TELESCOPE_ENABLED', false)", $source);
    }

    public function test_gate_denies_everyone_without_whitelist(): void
    {
        config(['telescope.allowed_ips' => []]);

        $this->assertFalse(Gate::check('viewTelescope', [null]));
        $this->assertFalse(Telescope::check(Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1'])));
    }

    public function test_gate_allows_only_whitelisted_ip(): void
    {
        config(['telescope.allowed_ips' => ['203.0.113.10']]);

        $this->app->instance('request', Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10']));
        $this->assertTrue(Gate::check('viewTelescope', [null]));

        $this->app->instance('request', Request::create('/telescope', 'GET', server: ['REMOTE_ADDR' => '198.51.100.7']));
        $this->assertFalse(Gate::check('viewTelescope', [null]));
    }
}
