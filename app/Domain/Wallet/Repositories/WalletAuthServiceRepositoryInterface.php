<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletAuthServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByName(int $walletId, string $name): ?array;

    /**
     * @return array<string, mixed>
     */
    public function updateWalletUserToken(int $walletUserId, string $token): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByToken(int $walletId, string $token): ?array;

    public function walletUserNameExists(int $walletId, string $name): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletUser(array $attributes): int;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function batchCreateWalletUsers(array $rows): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDevicesByWalletUserId(int $walletUserId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotifiesByWalletUserId(int $walletUserId): array;
}
