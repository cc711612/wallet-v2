<?php

declare(strict_types=1);

namespace App\Domain\Device\Repositories;

interface DeviceServiceRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByWalletUserId(int $walletUserId): array;

    /**
     * 依 token 與擁有者查詢裝置 ID。
     */
    public function findIdByTokenAndOwner(string $fcmToken, int $walletUserId, int $userId): ?int;

    /**
     * 更新裝置資料。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $deviceId, array $attributes): void;

    /**
     * 建立裝置資料。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): void;

    /**
     * 刪除裝置資料。
     */
    public function delete(int $deviceId): void;
}
