<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Device\Entities\DeviceEntity;
use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Wallet\Entities\WalletUserEntity;
use Illuminate\Support\Facades\Http;

class NotificationJobRepository implements NotificationJobRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUser(int $walletUserId): ?array
    {
        return WalletUserEntity::query()->find($walletUserId)?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserWithWallet(int $walletUserId): ?array
    {
        return WalletUserEntity::query()
            ->with('wallet')
            ->find($walletUserId)?->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveDevicesByOwner(?int $userId, int $walletUserId): array
    {
        return DeviceEntity::query()
            ->where('expired_at', '>', now())
            ->where(function ($query) use ($userId, $walletUserId): void {
                $query->where('wallet_user_id', $walletUserId);
                if ($userId !== null && $userId > 0) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->get(['wallet_user_id', 'user_id', 'fcm_token'])
            ->map(static fn (DeviceEntity $device): array => $device->toArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    public function sendFcmBatch(array $requestBody): bool
    {
        $notificationUrl = rtrim((string) config('services.notification.url', ''), '/');
        $notificationKey = (string) config('services.notification.key', '');
        if ($notificationUrl === '' || $notificationKey === '') {
            return false;
        }

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-API-KEY' => $notificationKey,
            ])
            ->post($notificationUrl.'/api/v1/firebase/batch', $requestBody);

        return $response->successful();
    }
}
