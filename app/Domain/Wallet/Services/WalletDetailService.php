<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use RuntimeException;

class WalletDetailService
{
    /**
     * @return void
     */
    public function __construct(
        private WalletDetailRepositoryInterface $walletDetailRepository,
        private WalletDetailQueryRepositoryInterface $walletDetailQueryRepository
    ) {}

    /**
     * 建立帳本明細。
     *
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array
    {
        $this->guardPublicExpenseNotNegative($walletDetail);
        $this->guardWalletUsersValid($walletDetail);

        return $this->walletDetailRepository->create($walletDetail);
    }

    /**
     * 取得帳本明細列表。
     *
     * @return array<string, mixed>
     */
    public function index(int $walletId, ?bool $isPersonal = null, ?int $walletUserId = null): array
    {
        $wallet = $this->walletDetailQueryRepository->findWallet($walletId);
        if ($wallet === null) {
            throw new RuntimeException('系統錯誤，請重新整理');
        }

        $details = $this->walletDetailQueryRepository->listDetails($walletId, $isPersonal, $walletUserId);
        $walletUsers = $this->walletDetailQueryRepository->listWalletUsers($walletId);
        $walletUserIds = array_values(array_map(static fn (array $walletUser): int => (int) ($walletUser['id'] ?? 0), $walletUsers));

        $details = array_map(static function (array $detail) use ($walletUserIds): array {
            if (
                (int) ($detail['type'] ?? 0) === WalletDetailType::PUBLIC_EXPENSE->value
                && (($detail['payment_user_id'] ?? null) === null)
            ) {
                $detail['users'] = $walletUserIds;
            }

            return $detail;
        }, $details);

        $publicDetails = array_values(array_filter(
            $details,
            static fn (array $detail): bool => (int) ($detail['type'] ?? 0) === WalletDetailType::PUBLIC_EXPENSE->value
        ));

        $income = array_reduce(
            $publicDetails,
            static fn (float $total, array $detail): float => (int) ($detail['symbol_operation_type_id'] ?? 0) === SymbolOperationType::INCREMENT->value
                ? $total + (float) ($detail['value'] ?? 0)
                : $total,
            0.0
        );

        $expenses = array_reduce(
            $publicDetails,
            static fn (float $total, array $detail): float => (int) ($detail['symbol_operation_type_id'] ?? 0) === SymbolOperationType::DECREMENT->value
                ? $total + (float) ($detail['value'] ?? 0)
                : $total,
            0.0
        );

        $walletUser = null;
        foreach ($walletUsers as $item) {
            if ((bool) ($item['is_admin'] ?? false) === true) {
                $walletUser = $item;

                break;
            }
        }

        return [
            'wallet' => [
                'id' => (int) $wallet['id'],
                'code' => (string) $wallet['code'],
                'title' => (string) $wallet['title'],
                'status' => (int) $wallet['status'],
                'mode' => (string) $wallet['mode'],
                'unit' => (string) $wallet['unit'],
                'wallet_user' => $walletUser,
                'properties' => $wallet['properties'],
                'created_at' => (string) ($wallet['created_at'] ?? ''),
                'updated_at' => (string) ($wallet['updated_at'] ?? ''),
                'details' => $details,
                'wallet_users' => $walletUsers,
                'total' => ['income' => $income, 'expenses' => $expenses],
            ],
        ];
    }

    /**
     * 取得單筆帳本明細。
     *
     * @return array<string, mixed>
     */
    public function show(int $walletId, int $detailId): array
    {
        $detail = $this->walletDetailQueryRepository->findDetail($walletId, $detailId);
        if ($detail === null) {
            throw new RuntimeException('參數有誤');
        }

        return [
            'wallet' => [
                'id' => $walletId,
                'wallet_detail' => $detail,
            ],
        ];
    }

    /**
     * 更新帳本明細。
     */
    public function update(WalletDetail $walletDetail, int $detailId): void
    {
        $this->guardPublicExpenseNotNegative($walletDetail);
        $this->guardWalletUsersValid($walletDetail);

        $attributes = $walletDetail->toPersistenceAttributes();

        $this->walletDetailQueryRepository->updateDetail($walletDetail->walletId(), $detailId, [
            'type' => $attributes['type'],
            'payment_wallet_user_id' => $attributes['payment_wallet_user_id'],
            'title' => $attributes['title'],
            'symbol_operation_type_id' => $attributes['symbol_operation_type_id'],
            'select_all' => (int) ((bool) $attributes['select_all']),
            'is_personal' => (int) ((bool) $attributes['is_personal']),
            'value' => $attributes['value'],
            'unit' => $attributes['unit'],
            'rates' => $attributes['rates'],
            'date' => $attributes['date'],
            'note' => $attributes['note'],
            'category_id' => $attributes['category_id'],
            'updated_by' => $attributes['updated_by'],
        ]);

        /** @var array<int, int> $users */
        $users = array_values(array_unique(array_map('intval', (array) ($attributes['users'] ?? []))));
        if ((bool) ($attributes['select_all'] ?? false) === true) {
            $users = array_values(array_map(
                static fn (array $walletUser): int => (int) ($walletUser['id'] ?? 0),
                $this->walletDetailQueryRepository->listWalletUsers($walletDetail->walletId())
            ));
        }

        $this->walletDetailQueryRepository->replaceDetailUsers($walletDetail->walletId(), $detailId, $users);
    }

    /**
     * 刪除帳本明細（建立者或 admin 才可刪除）。
     */
    public function destroy(int $walletId, int $detailId, int $walletUserId, bool $isAdmin): void
    {
        $detail = $this->walletDetailQueryRepository->findDetail($walletId, $detailId);
        if ($detail === null) {
            throw new RuntimeException('參數有誤');
        }

        if (! $isAdmin && (int) ($detail['created_by'] ?? 0) !== $walletUserId) {
            throw new RuntimeException('非admin');
        }

        $this->walletDetailQueryRepository->deleteDetail($walletId, $detailId);
    }

    /**
     * 結帳帳本明細。
     *
     * @param  array<int, int>  $detailIds
     */
    public function checkout(int $walletId, array $detailIds, int $walletUserId): void
    {
        if ($detailIds === []) {
            return;
        }

        $this->walletDetailQueryRepository->checkout($walletId, $detailIds, $walletUserId);
    }

    /**
     * 取消結帳帳本明細。
     */
    public function uncheckout(int $walletId, string $checkoutAt, int $walletUserId): void
    {
        $this->walletDetailQueryRepository->uncheckout($walletId, $checkoutAt, $walletUserId);
    }

    /**
     * 檢查公費扣款後不可為負數。
     */
    private function guardPublicExpenseNotNegative(WalletDetail $walletDetail): void
    {
        if (
            $walletDetail->type() !== WalletDetailType::PUBLIC_EXPENSE->value
            || $walletDetail->symbolOperationTypeId() !== SymbolOperationType::DECREMENT->value
        ) {
            return;
        }

        $walletBalance = $this->walletDetailRepository->getWalletBalance($walletDetail->walletId());
        $nextBalance = $walletBalance - $walletDetail->value();
        if ($nextBalance < 0) {
            throw new WalletDetailBusinessException('公費結算金額不得為負數');
        }
    }

    /**
     * 檢查分攤成員皆屬於帳本成員。
     */
    private function guardWalletUsersValid(WalletDetail $walletDetail): void
    {
        if ($walletDetail->type() === WalletDetailType::PUBLIC_EXPENSE->value || $walletDetail->selectAll()) {
            return;
        }

        $walletUserIds = $walletDetail->users();
        if ($walletUserIds === []) {
            throw new WalletDetailBusinessException('分攤成員有誤');
        }

        $exists = $this->walletDetailRepository->walletUsersExistInWallet($walletDetail->walletId(), $walletUserIds);
        if (! $exists) {
            throw new WalletDetailBusinessException('分攤成員有誤');
        }
    }
}
