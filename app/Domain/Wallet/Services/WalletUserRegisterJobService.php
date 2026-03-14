<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;

class WalletUserRegisterJobService
{
    /**
     * 建立帳本成員註冊工作服務。
     *
     * @return void
     */
    public function __construct(private WalletJobRepositoryInterface $walletJobRepository) {}

    /**
     * 同步全選明細的成員關聯資料。
     */
    public function syncSelectedDetailsForWalletUsers(int $walletId): void
    {
        if ($walletId <= 0) {
            return;
        }

        $walletUserIds = $this->walletJobRepository->listWalletUserIds($walletId);
        if ($walletUserIds === []) {
            return;
        }

        $detailIds = $this->walletJobRepository->listSelectAllDetailIds($walletId);
        foreach ($detailIds as $detailId) {
            $this->walletJobRepository->syncDetailUsersWithoutDetaching($detailId, $walletUserIds);
        }
    }
}
