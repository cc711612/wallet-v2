<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Device\Entities\DeviceEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Repositories\WalletAuthServiceRepositoryInterface;

class WalletAuthServiceRepository implements WalletAuthServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array
    {
        $wallet = WalletEntity::query()->where('code', $code)->first(['id', 'code']);

        return $wallet?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByName(int $walletId, string $name): ?array
    {
        $walletUser = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('name', $name)
            ->first();

        return $walletUser?->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateWalletUserToken(int $walletUserId, string $token): array
    {
        WalletUserEntity::query()->where('id', $walletUserId)->update(['token' => $token]);

        return (array) WalletUserEntity::query()->where('id', $walletUserId)->first()?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByToken(int $walletId, string $token): ?array
    {
        $walletUser = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('token', $token)
            ->first(['id', 'name', 'wallet_id', 'token']);

        return $walletUser?->toArray();
    }

    public function walletUserNameExists(int $walletId, string $name): bool
    {
        return WalletUserEntity::query()->where('wallet_id', $walletId)->where('name', $name)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletUser(array $attributes): int
    {
        $walletUser = WalletUserEntity::query()->create($attributes);

        return (int) $walletUser->id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function batchCreateWalletUsers(array $rows): void
    {
        if ($rows !== []) {
            WalletUserEntity::query()->insert($rows);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDevicesByWalletUserId(int $walletUserId): array
    {
        return DeviceEntity::query()
            ->where('wallet_user_id', $walletUserId)
            ->where('expired_at', '>', now())
            ->orderByDesc('updated_at')
            ->get(['id', 'user_id', 'wallet_user_id', 'platform', 'device_name', 'device_type', 'fcm_token', 'expired_at'])
            ->map(static fn (DeviceEntity $device): array => $device->toArray())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotifiesByWalletUserId(int $walletUserId): array
    {
        $walletUser = WalletUserEntity::query()->where('id', $walletUserId)->first(['id', 'name', 'wallet_id', 'notify_enable']);
        if ($walletUser === null) {
            return [];
        }

        $wallet = WalletEntity::query()->where('id', $walletUser->wallet_id)->first(['id', 'code']);

        return [[
            'id' => (int) $walletUser->id,
            'name' => (string) $walletUser->name,
            'wallet_id' => (int) $walletUser->wallet_id,
            'notify_enable' => (bool) ($walletUser->notify_enable ?? 0),
            'wallets' => [
                'id' => $wallet ? (int) $wallet->id : null,
                'code' => $wallet ? (string) $wallet->code : null,
            ],
        ]];
    }
}
