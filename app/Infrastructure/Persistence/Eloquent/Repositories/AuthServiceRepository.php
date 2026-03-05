<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Domain\Device\Entities\DeviceEntity;
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
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findLatestOwnedWalletByUserId(int $userId): ?array
    {
        $wallet = WalletEntity::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->first(['id', 'code']);

        return $wallet?->toArray();
    }

    /**
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsersByUserId(int $userId): array
    {
        return WalletUserEntity::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get([
                'id',
                'wallet_id',
                'user_id',
                'name',
                'token',
                'is_admin',
                'notify_enable',
                'agent',
                'ip',
                'created_at',
                'updated_at',
            ])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'wallet_id' => (int) $item->wallet_id,
                'user_id' => $item->user_id === null ? null : (int) $item->user_id,
                'name' => (string) $item->name,
                'token' => (string) ($item->token ?? ''),
                'is_admin' => (int) ($item->is_admin ?? 0),
                'notify_enable' => (int) ($item->notify_enable ?? 0),
                'agent' => (string) ($item->agent ?? ''),
                'ip' => (string) ($item->ip ?? ''),
                'created_at' => (string) $item->created_at,
                'updated_at' => (string) $item->updated_at,
            ])
            ->all();
    }

    /**
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listActiveDevicesByUserId(int $userId): array
    {
        return DeviceEntity::query()
            ->where('user_id', $userId)
            ->where('expired_at', '>', now())
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'user_id',
                'wallet_user_id',
                'platform',
                'device_name',
                'device_type',
                'fcm_token',
                'expired_at',
                'created_at',
                'updated_at',
            ])
            ->map(static fn (DeviceEntity $item): array => [
                'id' => (int) $item->id,
                'user_id' => $item->user_id === null ? null : (int) $item->user_id,
                'wallet_user_id' => $item->wallet_user_id === null ? null : (int) $item->wallet_user_id,
                'platform' => (string) ($item->platform ?? ''),
                'device_name' => (string) ($item->device_name ?? ''),
                'device_type' => (string) ($item->device_type ?? ''),
                'fcm_token' => (string) ($item->fcm_token ?? ''),
                'expired_at' => (string) ($item->expired_at ?? ''),
                'created_at' => (string) $item->created_at,
                'updated_at' => (string) $item->updated_at,
            ])
            ->all();
    }

    /**
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listNotifiesByUserId(int $userId): array
    {
        return WalletUserEntity::query()
            ->with(['wallet:id,code'])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get(['id', 'name', 'wallet_id', 'notify_enable'])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'wallet_id' => (int) $item->wallet_id,
                'notify_enable' => (int) ($item->notify_enable ?? 0),
                'wallets' => [
                    'id' => $item->wallet ? (int) $item->wallet->id : null,
                    'code' => $item->wallet ? (string) $item->wallet->code : null,
                ],
            ])
            ->all();
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
