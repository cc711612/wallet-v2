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
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array
    {
        $user = UserEntity::query()->where('account', $account)->first();

        return $user?->toArray();
    }

    public function updateUserToken(int $userId, string $token): void
    {
        UserEntity::query()->where('id', $userId)->update(['token' => $token]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array
    {
        $walletUser = WalletUserEntity::query()->where('user_id', $userId)->orderByDesc('id')->first();

        return $walletUser?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array
    {
        $wallet = WalletEntity::query()->where('id', $walletId)->first(['id', 'code']);

        return $wallet?->toArray();
    }

    public function accountExists(string $account): bool
    {
        return UserEntity::query()->where('account', $account)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): int
    {
        $user = UserEntity::query()->create($attributes);

        return (int) $user->id;
    }
}
