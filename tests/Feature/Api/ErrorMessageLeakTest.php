<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Services\WalletDetailService;
use Illuminate\Database\QueryException;
use Mockery\MockInterface;
use PDOException;
use RuntimeException;
use Tests\Concerns\FakesApiAuthentication;
use Tests\TestCase;

/**
 * P0-10：非業務例外（QueryException 等）不得把原始訊息回給 client；業務訊息維持原樣。
 */
class ErrorMessageLeakTest extends TestCase
{
    use FakesApiAuthentication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeApiAuthentication();
    }

    public function test_query_exception_message_is_not_returned(): void
    {
        $this->mock(WalletDetailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andThrow(new QueryException(
                'mysql',
                'select * from wallet_details where secret_column = ?',
                ['x'],
                new PDOException('SQLSTATE[HY000] [2002] Connection refused db-host-internal')
            ));
        });

        $response = $this->withToken('test')->getJson('/api/wallet/1/detail');

        $response->assertStatus(500)->assertJsonPath('message', '系統錯誤，請稍後再試');
        $this->assertStringNotContainsString('secret_column', (string) $response->getContent());
        $this->assertStringNotContainsString('db-host-internal', (string) $response->getContent());
    }

    public function test_business_runtime_exception_message_is_kept(): void
    {
        $this->mock(WalletDetailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('show')->andThrow(new RuntimeException('參數有誤'));
        });

        $this->withToken('test')->getJson('/api/wallet/1/detail/99')
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '參數有誤');
    }

    public function test_health_check_does_not_expose_exception_message(): void
    {
        $this->app['config']->set('database.connections.sqlite.database', '/nonexistent/dir/db.sqlite');
        $this->app['db']->purge('sqlite');

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('data.db.ok', false)
            ->assertJsonPath('data.db.message', 'failed');
        $this->assertStringNotContainsString('/nonexistent/dir', (string) $response->getContent());
    }
}
