<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;

class WalletUserRegisterJobService
{
    public function __construct(private WalletJobRepositoryInterface $walletJobRepository) {}

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
