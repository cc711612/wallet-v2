<?php

declare(strict_types=1);

namespace App\Domain\Device\Services;

use App\Domain\Device\Repositories\DeviceServiceRepositoryInterface;
use Illuminate\Support\Carbon;

class DeviceService
{
    /**
     * @return void
     */
    public function __construct(private DeviceServiceRepositoryInterface $deviceRepository) {}

    /**
     * @param  array<string, mixed>  $owner
     * @return array<int, array<string, mixed>>
     */
    public function index(array $owner): array
    {
        $walletUserId = (int) ($owner['id'] ?? 0);

        if ($walletUserId > 0) {
            /** @var array<int, array<string, mixed>> $devices */
            $devices = $this->deviceRepository->listByWalletUserId($walletUserId);

            return $devices;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        $walletUserId = (int) ($payload['wallet_user_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $fcmToken = (string) ($payload['fcm_token'] ?? '');

        $currentId = $this->deviceRepository->findIdByTokenAndOwner($fcmToken, $walletUserId, $userId);

        $upsertPayload = [
            'user_id' => $userId ?: null,
            'wallet_user_id' => $walletUserId ?: null,
            'platform' => (string) ($payload['platform'] ?? ''),
            'device_name' => (string) ($payload['device_name'] ?? ''),
            'device_type' => (string) ($payload['device_type'] ?? ''),
            'fcm_token' => $fcmToken,
            'expired_at' => $this->normalizeExpiredAt($payload['expired_at'] ?? null),
            'updated_at' => now(),
        ];

        if ($currentId !== null) {
            $this->deviceRepository->update($currentId, $upsertPayload);
        } else {
            $this->deviceRepository->create($upsertPayload);
        }

        return ['stored' => true, 'fcm_token' => $fcmToken];
    }

    /**
     * 前端送的 expired_at 是 UTC（不帶時區的 "Y-m-d H:i:s"），轉成 app.timezone 後才寫入 DB。
     */
    private function normalizeExpiredAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')
            ->setTimezone((string) config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * 只能刪除屬於自己帳本成員身分的裝置；回傳是否有刪到。
     *
     * @param  array<int, int>  $ownerWalletUserIds
     */
    public function destroy(int $deviceId, array $ownerWalletUserIds): bool
    {
        if ($ownerWalletUserIds === []) {
            return false;
        }

        return $this->deviceRepository->deleteOwnedBy($deviceId, $ownerWalletUserIds) > 0;
    }
}
