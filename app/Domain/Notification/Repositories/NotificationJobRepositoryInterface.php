<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

interface NotificationJobRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUser(int $walletUserId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserWithWallet(int $walletUserId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveDevicesByOwner(?int $userId, int $walletUserId): array;

    /**
     * 發送 FCM 批次通知。
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function sendFcmBatch(array $requestBody): bool;
}
