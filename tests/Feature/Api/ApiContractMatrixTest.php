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

class ApiContractMatrixTest extends TestCase
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
            $mock->shouldReceive('index')->andReturn(['paginate' => ['page' => 1, 'page_count' => 15], 'wallets' => []]);
            $mock->shouldReceive('store')->andReturn(['wallet' => ['id' => 1, 'code' => 'WALLET001', 'title' => 'demo', 'mode' => 'multi', 'status' => true, 'created_at' => now()->toDateTimeString()]]);
            $mock->shouldReceive('update')->andReturn(['updated' => true]);
            $mock->shouldReceive('destroy')->andReturn('錢包已成功刪除');
            $mock->shouldReceive('bind')->andReturn('綁定成功');
            $mock->shouldReceive('calculation')->andReturn(['wallet' => ['id' => 1], 'users' => [], 'details' => []]);
        });

        $this->mock(WalletAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('login')->andReturn(['id' => 10, 'name' => 'member', 'wallet_id' => 1, 'member_token' => 'mtk', 'jwt' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001'], 'devices' => [], 'notifies' => []]);
            $mock->shouldReceive('token')->andReturn(['id' => 10, 'name' => 'member', 'wallet_id' => 1, 'member_token' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001']]);
            $mock->shouldReceive('register')->andReturn(['id' => 11, 'name' => 'new', 'member_token' => 'mtk', 'wallet' => ['id' => 1, 'code' => 'WALLET001']]);
            $mock->shouldReceive('registerBatch')->andReturn(['count' => 2]);
        });

        $this->mock(WalletUserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn(['wallet' => ['id' => 1, 'code' => 'WALLET001', 'users' => []]]);
            $mock->shouldReceive('update')->andReturn(['wallet_user_id' => 1]);
            $mock->shouldReceive('destroy')->andReturn(['deleted' => true]);
        });

        $this->mock(DeviceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn([]);
            $mock->shouldReceive('store')->andReturn(['stored' => true]);
            $mock->shouldReceive('destroy')->andReturn(['deleted' => true]);
        });

        $this->mock(WalletDetailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->andReturn(['wallet' => ['id' => 1], 'details' => [], 'wallet_users' => [], 'total' => ['income' => 0, 'expenses' => 0]]);
            $mock->shouldReceive('show')->andReturn(['wallet' => ['id' => 1, 'wallet_detail' => (object) []]]);
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

    public function test_core_api_modules_follow_response_envelope_contract(): void
    {
        $cases = [
            ['POST', '/api/auth/register', ['account' => 'a1', 'password' => '123456', 'name' => 'Roy']],
            ['POST', '/api/auth/register/token', ['token' => 'social-token']],
            ['POST', '/api/auth/thirdParty/login', ['provider' => 'line', 'token' => 'tk']],
            ['POST', '/api/auth/thirdParty/checkBind', ['socialType' => 'line', 'socialTypeValue' => 'u123']],
            ['GET', '/api/option/category', []],
            ['POST', '/api/wallet/auth/login', ['code' => 'W001', 'name' => 'member-a']],
            ['POST', '/api/wallet/auth/login/token', ['code' => 'W001', 'member_token' => 'mtk']],
            ['POST', '/api/wallet/auth/register', ['code' => 'W001', 'name' => 'member-b']],
            ['POST', '/api/wallet/auth/register/batch', ['code' => 'W001', 'name' => ['a', 'b']]],
            ['POST', '/api/wallet', ['title' => 'Team Wallet', 'mode' => 'single']],
            ['PUT', '/api/wallet/1', ['title' => 'Team Wallet 2', 'status' => true]],
            ['POST', '/api/wallet/1/calculation', []],
            ['GET', '/api/device', []],
            ['POST', '/api/device', ['platform' => 'ios', 'device_name' => 'iPhone', 'device_type' => 'mobile', 'fcm_token' => 'abc']],
            ['DELETE', '/api/device/1', []],
            ['POST', '/api/wallet/1/detail/list', []],
            ['GET', '/api/wallet/1/detail/1', []],
            ['PUT', '/api/wallet/1/detail/checkout', ['checkout_id' => [1, 2]]],
            ['PUT', '/api/wallet/1/detail/undo_checkout', ['checkout_at' => now()->toDateTimeString()]],
            ['POST', '/api/webhook/line/notify', ['code' => 'W001']],
            ['POST', '/api/gemini/chat', ['messages' => [['role' => 'user', 'content' => 'hello']]]],
        ];

        /** @var array{0:string,1:string,2:array<string, mixed>} $case */
        foreach ($cases as $case) {
            [$method, $uri, $payload] = $case;
            $response = $this->json($method, $uri, $payload, ['Authorization' => 'Bearer test']);

            $response->assertOk();

            if (str_starts_with($uri, '/api/gemini/models')) {
                continue;
            }

            $response->assertJsonStructure(['status', 'code', 'message', 'data']);
        }
    }
}
