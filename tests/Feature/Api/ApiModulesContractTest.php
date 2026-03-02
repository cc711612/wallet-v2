<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Auth\Services\AuthService;
use App\Domain\Device\Services\DeviceService;
use App\Domain\Gemini\Services\GeminiService;
use App\Domain\Option\Services\OptionService;
use App\Domain\Social\Services\SocialService;
use App\Domain\Wallet\Services\WalletAuthService;
use App\Domain\Wallet\Services\WalletDetailService;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Wallet\Services\WalletUserService;
use App\Domain\Webhook\Services\LineWebhookService;
use Mockery\MockInterface;
use Tests\TestCase;

class ApiModulesContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('login')->andReturn(['id' => 1, 'name' => 'tester', 'member_token' => 'tk', 'jwt' => 'tk', 'wallet' => ['id' => 1, 'code' => 'WALLET001'], 'walletUsers' => [], 'devices' => [], 'notifies' => []]);
            $mock->shouldReceive('register')->andReturn(['id' => 1, 'name' => 'tester', 'member_token' => 'tk', 'jwt' => 'tk', 'wallet' => ['id' => 1, 'code' => 'WALLET001'], 'walletUsers' => [], 'devices' => [], 'notifies' => []]);
            $mock->shouldReceive('cache')->andReturn([]);
            $mock->shouldReceive('logout')->andReturn(['token' => '']);
        });

        $this->mock(SocialService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkBind')->andReturn(['action' => 'not bind', 'token' => 'social-token']);
            $mock->shouldReceive('bind')->andReturn(['bound' => true]);
            $mock->shouldReceive('unBind')->andReturn(['unbound' => true]);
            $mock->shouldReceive('listForUser')->andReturn([]);
        });

        $this->mock(OptionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exchangeRate')->andReturn(['option' => ['TWD'], 'rates' => ['TWD' => 1], 'unit' => 'TWD', 'updated_at' => now()->toDateString()]);
            $mock->shouldReceive('categories')->andReturn([]);
        });

        $this->mock(WalletService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn([
                'paginate' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 1],
                'wallets' => [[
                    'id' => 1,
                    'title' => 'demo',
                    'code' => 'WALLET001',
                    'status' => 1,
                    'unit' => 'TWD',
                    'mode' => 'multi',
                    'properties' => ['unitConfigurable' => false, 'decimalPlaces' => 0],
                    'user' => ['id' => 1, 'name' => 'tester'],
                ]],
            ]);
            $mock->shouldReceive('store')->andReturn(['wallet' => ['id' => 1, 'code' => 'WALLET001', 'title' => 'demo', 'mode' => 'multi', 'status' => true, 'created_at' => now()->toDateTimeString()]]);
            $mock->shouldReceive('update')->andReturn(['updated' => true]);
            $mock->shouldReceive('destroy')->andReturn('錢包已成功刪除');
            $mock->shouldReceive('bind')->andReturn('綁定成功');
            $mock->shouldReceive('calculation')->andReturn([
                'wallet' => [
                    'id' => 1,
                    'total' => [
                        'public' => ['income' => 0, 'expenses' => 0],
                        'income' => 0,
                        'expenses' => 0,
                    ],
                    'users' => [],
                    'details' => [],
                ],
            ]);
        });

        $this->mock(WalletAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('login')->andReturn(['id' => 10, 'name' => 'member', 'wallet_id' => 1, 'member_token' => 'mtk', 'jwt' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001'], 'devices' => [], 'notifies' => []]);
            $mock->shouldReceive('token')->andReturn(['id' => 10, 'name' => 'member', 'wallet_id' => 1, 'member_token' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001']]);
            $mock->shouldReceive('register')->andReturn(['id' => 11, 'name' => 'new', 'member_token' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001']]);
            $mock->shouldReceive('registerBatch')->andReturn(['count' => 2]);
        });

        $this->mock(WalletUserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn(['wallet' => ['users' => []]]);
            $mock->shouldReceive('update')->andReturn(['wallet_user_id' => 1]);
            $mock->shouldReceive('destroy')->andReturn(['deleted' => true]);
        });

        $this->mock(DeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn([]);
            $mock->shouldReceive('store')->andReturn(['stored' => true]);
            $mock->shouldReceive('destroy')->andReturn(['deleted' => true]);
        });

        $this->mock(WalletDetailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn([
                'wallet' => [
                    'id' => 1,
                    'code' => 'WALLET001',
                    'title' => 'demo',
                    'status' => 1,
                    'mode' => 'multi',
                    'unit' => 'TWD',
                    'wallet_user' => ['id' => 10, 'wallet_id' => 1, 'user_id' => 1, 'name' => 'tester', 'is_admin' => true, 'notify_enable' => true],
                    'properties' => ['unitConfigurable' => false, 'decimalPlaces' => 0],
                    'details' => [],
                    'wallet_users' => [],
                    'total' => ['income' => 0, 'expenses' => 0],
                ],
            ]);
            $mock->shouldReceive('show')->andReturn(['wallet' => ['id' => 1, 'wallet_detail' => ['id' => 1, 'users' => []]]]);
            $mock->shouldReceive('checkout')->andReturnNull();
            $mock->shouldReceive('uncheckout')->andReturnNull();
            $mock->shouldReceive('update')->andReturnNull();
            $mock->shouldReceive('destroy')->andReturnNull();
        });

        $this->mock(LineWebhookService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('store')->andReturn(['received' => true]);
            $mock->shouldReceive('notify')->andReturn(['queued' => true]);
            $mock->shouldReceive('notifyBindUrl')->andReturn(['url' => 'https://example.test/line/bind']);
        });

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->andReturn(['success' => true, 'text' => 'hello', 'raw_response' => []]);
            $mock->shouldReceive('chat')->andReturn(['success' => true, 'text' => 'hello', 'raw_response' => []]);
            $mock->shouldReceive('models')->andReturn([['name' => 'gemini-2.0-flash']]);
        });
    }

    public function test_auth_login_endpoint_returns_standard_envelope(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'account' => 'roy',
            'password' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.wallet.code', 'WALLET001');
    }

    public function test_wallet_list_endpoint_is_available_under_verify_api_middleware(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer test')->getJson('/api/wallet');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['paginate', 'wallets']])
            ->assertJsonPath('data.paginate.current_page', 1)
            ->assertJsonPath('data.paginate.last_page', 1)
            ->assertJsonPath('data.paginate.per_page', 50)
            ->assertJsonPath('data.wallets.0.user.name', 'tester');
    }

    public function test_option_and_gemini_modules_are_available(): void
    {
        $optionResponse = $this->getJson('/api/option/exchangeRate?unit=TWD');
        $optionResponse->assertOk()->assertJsonPath('status', true);

        $geminiResponse = $this->postJson('/api/gemini/generate', [
            'prompt' => 'hello',
        ]);
        $geminiResponse->assertOk()->assertJsonPath('data.success', true);
    }

    public function test_auth_cache_uses_legacy_null_message_contract(): void
    {
        $response = $this->getJson('/api/auth/cache');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', null)
            ->assertJsonPath('data', []);
    }

    public function test_wallet_user_index_returns_bad_request_envelope_when_service_throws(): void
    {
        $this->mock(WalletUserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andThrow(new \RuntimeException('帳本驗證碼錯誤'));
        });

        $response = $this->postJson('/api/wallet/user', [
            'code' => 'NOT_EXIST',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '帳本驗證碼錯誤');
    }

    public function test_option_exchange_rate_does_not_expose_unit_field(): void
    {
        $response = $this->getJson('/api/option/exchangeRate?unit=TWD');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.unit');
    }

    public function test_wallet_calculation_matches_legacy_nested_wallet_contract(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer test')->postJson('/api/wallet/1/calculation', []);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.wallet.id', 1)
            ->assertJsonPath('data.wallet.total.public.income', 0)
            ->assertJsonPath('data.wallet.total.public.expenses', 0)
            ->assertJsonMissingPath('data.users')
            ->assertJsonMissingPath('data.details');
    }

    public function test_wallet_detail_list_matches_legacy_wallet_wrapped_contract(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer test')->postJson('/api/wallet/1/detail/list', []);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.wallet.code', 'WALLET001')
            ->assertJsonPath('data.wallet.wallet_user.name', 'tester')
            ->assertJsonPath('data.wallet.total.income', 0)
            ->assertJsonMissingPath('data.is_personal');
    }

    public function test_wallet_detail_show_returns_legacy_bad_request_envelope_when_not_found(): void
    {
        $this->mock(WalletDetailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('show')->andThrow(new \RuntimeException('參數有誤'));
            $mock->shouldReceive('index')->andReturn(['wallet' => ['id' => 1]]);
            $mock->shouldReceive('checkout')->andReturnNull();
            $mock->shouldReceive('uncheckout')->andReturnNull();
            $mock->shouldReceive('update')->andReturnNull();
            $mock->shouldReceive('destroy')->andReturnNull();
            $mock->shouldReceive('create')->andReturn([]);
        });

        $response = $this->withHeader('Authorization', 'Bearer test')->getJson('/api/wallet/1/detail/999999');

        $response
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '參數有誤');
    }
}
