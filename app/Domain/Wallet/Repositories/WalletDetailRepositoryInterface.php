<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

use App\Domain\Wallet\Entities\WalletDetail;

interface WalletDetailRepositoryInterface
{
    public function getWalletBalance(int $walletId): float;

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool;

    /**
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array;
}
