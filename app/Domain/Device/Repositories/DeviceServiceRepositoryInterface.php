<?php

declare(strict_types=1);

namespace App\Domain\Device\Repositories;

interface DeviceServiceRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByWalletUserId(int $walletUserId): array;

    public function findIdByTokenAndOwner(string $fcmToken, int $walletUserId, int $userId): ?int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $deviceId, array $attributes): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): void;

    public function delete(int $deviceId): void;
}
