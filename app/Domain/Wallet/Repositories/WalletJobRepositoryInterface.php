<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletJobRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findUserById(int $userId): ?array;

    /**
     * @return array<int, int>
     */
    public function listWalletUserIds(int $walletId): array;

    /**
     * @return array<int, int>
     */
    public function listSelectAllDetailIds(int $walletId): array;

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function syncDetailUsersWithoutDetaching(int $detailId, array $walletUserIds): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByWalletAndUser(int $walletId, int $userId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findAdminWalletUserByWalletId(int $walletId): ?array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletDetail(array $attributes): int;

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function syncDetailUsers(int $detailId, array $walletUserIds): void;
}
