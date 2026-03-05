<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

use App\Domain\Wallet\Entities\WalletDetail;

interface WalletDetailRepositoryInterface
{
    /**
     * 取得帳本公費餘額。
     *
     * @param  int  $walletId
     * @return float
     */
    public function getWalletBalance(int $walletId): float;

    /**
     * 檢查分攤成員是否都在帳本中。
     *
     * @param  int  $walletId
     * @param  array<int, int>  $walletUserIds
     * @return bool
     */
    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool;

    /**
     * 建立帳本明細。
     *
     * @param  WalletDetail  $walletDetail
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array;
}
