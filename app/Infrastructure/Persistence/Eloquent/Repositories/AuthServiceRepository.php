<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;

class AuthServiceRepository implements AuthServiceRepositoryInterface
{
    /**
     * @param  string  $account
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array
    {
        $user = UserEntity::query()
            ->where('account', $account)
            ->first([
                'id',
                'name',
                'account',
                'password',
                'token',
                'agent',
                'ip',
                'created_at',
                'updated_at',
            ]);

        if ($user === null) {
            return null;
        }

        $user->makeVisible(['password', 'token']);

        return $user->toArray();
    }

    /**
     * @param  int  $userId
     * @param  string  $token
     * @return void
     * Rotate member token after login/logout.
     */
    public function updateUserToken(int $userId, string $token): void
    {
        UserEntity::query()->where('id', $userId)->update(['token' => $token]);
    }

    /**
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return void
     * Update login client metadata from auth request.
     */
    public function updateUserAgentIp(int $userId, string $agent, string $ip): void
    {
        UserEntity::query()->where('id', $userId)->update([
            'agent' => $agent,
            'ip' => $ip,
        ]);
    }

    /**
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array
    {
        $walletUser = WalletUserEntity::query()->where('user_id', $userId)->orderByDesc('id')->first();

        return $walletUser?->toArray();
    }

    /**
     * @param  int  $walletId
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array
    {
        $wallet = WalletEntity::query()->where('id', $walletId)->first(['id', 'code']);

        return $wallet?->toArray();
    }

    /**
     * @param  string  $account
     * @return bool
     * Check whether account already exists.
     */
    public function accountExists(string $account): bool
    {
        return UserEntity::query()->where('account', $account)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return int
     */
    public function createUser(array $attributes): int
    {
        $user = UserEntity::query()->create($attributes);

        return (int) $user->id;
    }

    /**
     * @param  int  $walletUserId
     * @return array<string, mixed>|null
     */
    public function findWalletUserById(int $walletUserId): ?array
    {
        $walletUser = WalletUserEntity::query()->where('id', $walletUserId)->first();

        return $walletUser?->toArray();
    }

    /**
     * @param  int  $walletId
     * @param  int  $userId
     * @return bool
     * Determine whether user already has a wallet member row.
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @param  int  $walletUserId
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return bool
     * Bind invited wallet member row to current user.
     */
    public function bindWalletUser(int $walletUserId, int $userId, string $agent, string $ip): bool
    {
        $updated = WalletUserEntity::query()
            ->where('id', $walletUserId)
            ->whereNull('user_id')
            ->update([
                'user_id' => $userId,
                'agent' => $agent,
                'ip' => $ip,
            ]);

        return $updated > 0;
    }
}
