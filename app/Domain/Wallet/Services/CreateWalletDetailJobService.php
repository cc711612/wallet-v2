<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use Illuminate\Support\Arr;

class CreateWalletDetailJobService
{
    /**
     * 建立帳本明細工作服務。
     *
     * @return void
     */
    public function __construct(
        private WalletJobRepositoryInterface $walletJobRepository,
        private WalletDetailService $walletDetailService,
    ) {}

    /**
     * 建立公費支出明細。
     *
     * 原先是在 queued job 內透過 HTTP loopback 呼叫 /api/wallet/{id}/detail，
     * 失敗才 fallback 直寫 DB；現改為直接呼叫 domain 層 WalletDetailService，
     * 收斂成單一路徑（不再有 HTTP 呼叫，也不再需要另一套直寫 DB 的 fallback邏輯）。
     * 若寫入失敗（例如業務規則例外或 DB 例外），交由例外往上拋，
     * 讓 job 本身的 $tries/$backoff 重試機制處理。
     *
     * @param  array<string, mixed>  $params
     */
    public function createGeneralExpenseDetail(int $userId, int $walletId, array $params): void
    {
        $user = $this->walletJobRepository->findUserById($userId);
        if ($user === null) {
            return;
        }

        $walletUser = $this->walletJobRepository->findWalletUserByWalletAndUser($walletId, $userId)
            ?? $this->walletJobRepository->findAdminWalletUserByWalletId($walletId);

        if ($walletUser === null) {
            return;
        }

        $walletUserId = (int) ($walletUser['id'] ?? 0);

        $walletDetail = WalletDetail::fromPayload([
            'wallet' => $walletId,
            'wallet_user_id' => $walletUserId,
            'type' => 2,
            'symbol_operation_type_id' => 2,
            'title' => (string) Arr::get($params, 'title', ''),
            'value' => (int) Arr::get($params, 'amount', 0),
            'unit' => (string) Arr::get($params, 'unit', 'TWD'),
            'select_all' => true,
            'is_personal' => false,
            'payment_wallet_user_id' => $walletUserId,
            'date' => (string) Arr::get($params, 'date', now()->format('Y-m-d')),
            'category_id' => Arr::get($params, 'categoryId'),
            'users' => [],
            'splits' => [],
        ]);

        $this->walletDetailService->create($walletDetail);
    }
}
