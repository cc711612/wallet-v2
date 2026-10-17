<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Auth\Services\VerifyApiService;
use App\Domain\Wallet\Services\VerifyWalletMemberService;
use Mockery\MockInterface;

/**
 * 契約測試用：以 mock 取代 token 解析，middleware 本身的判斷邏輯照常執行。
 * 不測認證的測試才使用；認證行為請見 tests/Feature/Auth/MiddlewareAuthTest。
 */
trait FakesApiAuthentication
{
    /**
     * @param  array<int, int>  $walletIds  目前使用者所屬的帳本 id
     */
    protected function fakeApiAuthentication(int $userId = 1, int $walletUserId = 100, array $walletIds = [1]): void
    {
        $user = ['id' => $userId, 'name' => 'testing-user', 'token' => 'testing-token'];

        $walletUsers = [];
        foreach ($walletIds as $walletId) {
            $walletUsers[$walletId] = [
                'id' => $walletUserId,
                'wallet_id' => $walletId,
                'name' => 'testing-member',
                'is_admin' => 1,
                'user_id' => $userId,
            ];
        }

        $this->mock(VerifyApiService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('resolveUserByJwt')->andReturn($user);
            $mock->shouldReceive('resolveUserByToken')->andReturn($user);
        });

        $this->mock(VerifyWalletMemberService::class, function (MockInterface $mock) use ($walletUsers): void {
            $mock->shouldReceive('resolveWalletUsersByJwt')->andReturn($walletUsers);
            $mock->shouldReceive('resolveWalletUserByToken')->andReturn(reset($walletUsers) ?: null);
        });
    }
}
