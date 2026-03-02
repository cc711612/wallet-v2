<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Device\Entities\DeviceEntity;
use App\Domain\Device\Repositories\DeviceServiceRepositoryInterface;

class DeviceServiceRepository implements DeviceServiceRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByWalletUserId(int $walletUserId): array
    {
        return DeviceEntity::query()
            ->where('wallet_user_id', $walletUserId)
            ->get(['id', 'platform', 'device_name', 'device_type', 'fcm_token', 'expired_at'])
            ->map(static fn (DeviceEntity $item): array => [
                'id' => (int) $item->id,
                'platform' => (string) $item->platform,
                'device_name' => (string) $item->device_name,
                'device_type' => (string) $item->device_type,
                'fcm_token' => (string) $item->fcm_token,
                'expired_at' => $item->expired_at,
            ])
            ->all();
    }

    public function findIdByTokenAndOwner(string $fcmToken, int $walletUserId, int $userId): ?int
    {
        $query = DeviceEntity::query()->where('fcm_token', $fcmToken);

        $query->where(function ($inner) use ($walletUserId, $userId): void {
            if ($walletUserId > 0) {
                $inner->where('wallet_user_id', $walletUserId);
            }
            if ($userId > 0) {
                $inner->orWhere('user_id', $userId);
            }
        });

        $device = $query->first(['id']);

        return $device ? (int) $device->id : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $deviceId, array $attributes): void
    {
        DeviceEntity::query()->where('id', $deviceId)->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): void
    {
        DeviceEntity::query()->create($attributes);
    }

    public function delete(int $deviceId): void
    {
        DeviceEntity::query()->where('id', $deviceId)->delete();
    }
}
