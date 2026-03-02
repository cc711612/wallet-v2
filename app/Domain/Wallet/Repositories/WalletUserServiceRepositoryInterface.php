<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletUserServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWalletUser(int $walletUserId, array $attributes): void;

    public function deleteWalletUser(int $walletId, int $walletUserId): void;
}
