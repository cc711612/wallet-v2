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
     * 統一查詢使用者並補出 hidden 欄位。
     *
     * @param  array<int, string>  $columns
     * @return array<string, mixed>|null
     */
    private function findUser(array $columns, string $field, int|string $value): ?array
    {
        $user = UserEntity::query()->where($field, $value)->first($columns);
        if ($user === null) {
            return null;
        }

        $user->makeVisible(['password', 'token']);

        return $user->toArray();
    }

    /**
     * 依帳號取得使用者資料。
     *
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array
    {
        return $this->findUser([
            'id',
            'name',
            'account',
            'password',
            'token',
            'agent',
            'ip',
            'created_at',
            'updated_at',
        ], 'account', $account);
    }

    /**
     * 依使用者 ID 取得使用者資料。
     *
     * @return array<string, mixed>|null
     */
    public function findUserById(int $userId): ?array
    {
        return $this->findUser([
            'id',
            'name',
            'account',
            'password',
            'token',
            'agent',
            'ip',
            'created_at',
            'updated_at',
        ], 'id', $userId);
    }

    /**
     * 更新登入 token（member_token）。
     */
    public function updateUserToken(int $userId, string $token): void
    {
        UserEntity::query()->where('id', $userId)->update(['token' => $token]);
    }

    /**
     * 更新使用者登入裝置資訊。
     */
    public function updateUserAgentIp(int $userId, string $agent, string $ip): void
    {
        UserEntity::query()->where('id', $userId)->update([
            'agent' => $agent,
            'ip' => $ip,
        ]);
    }

    /**
     * 取得使用者最新一筆帳本成員資料。
     *
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array
    {
        $walletUser = WalletUserEntity::query()->where('user_id', $userId)->orderByDesc('id')->first();

        return $walletUser?->toArray();
    }

    /**
     * 取得指定帳本摘要資料。
     *
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array
    {
        $wallet = WalletEntity::query()->where('id', $walletId)->first(['id', 'code']);

        return $wallet?->toArray();
    }

    /**
     * 取得使用者擁有的最新帳本。
     *
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
     * 取得使用者可見的帳本成員清單。
     *
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
                'deleted_at',
            ])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'wallet_id' => (int) $item->wallet_id,
                'user_id' => $item->user_id === null ? null : (int) $item->user_id,
                'name' => (string) $item->name,
                'token' => (string) ($item->token ?? ''),
                'is_admin' => (bool) ($item->is_admin ?? false),
                'notify_enable' => (bool) ($item->notify_enable ?? false),
                'agent' => (string) ($item->agent ?? ''),
                'ip' => (string) ($item->ip ?? ''),
                'created_at' => (string) $item->created_at,
                'updated_at' => (string) $item->updated_at,
                'deleted_at' => $item->deleted_at ? (string) $item->deleted_at : null,
            ])
            ->all();
    }

    /**
     * 取得使用者有效裝置清單。
     *
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
                'deleted_at',
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
                'deleted_at' => $item->deleted_at ? (string) $item->deleted_at : null,
            ])
            ->all();
    }

    /**
     * 取得使用者通知設定清單。
     *
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
                'notify_enable' => (bool) ($item->notify_enable ?? false),
                'wallets' => [
                    'id' => $item->wallet ? (int) $item->wallet->id : null,
                    'code' => $item->wallet ? (string) $item->wallet->code : null,
                ],
            ])
            ->all();
    }

    /**
     * 檢查帳號是否已存在。
     */
    public function accountExists(string $account): bool
    {
        return UserEntity::query()->where('account', $account)->exists();
    }

    /**
     * 建立使用者資料。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): int
    {
        $user = UserEntity::query()->create($attributes);

        return (int) $user->id;
    }

    /**
     * 依帳本成員 ID 取得資料。
     *
     * @return array<string, mixed>|null
     */
    public function findWalletUserById(int $walletUserId): ?array
    {
        $walletUser = WalletUserEntity::query()->where('id', $walletUserId)->first();

        return $walletUser?->toArray();
    }

    /**
     * 檢查使用者是否已綁定到指定帳本。
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 將邀請中的帳本成員綁定到正式使用者。
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
