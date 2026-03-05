<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletDetailQueryRepositoryInterface
{
    /**
     * 取得帳本明細清單。
     *
     * @param  int  $walletId
     * @param  bool|null  $isPersonal
     * @param  int|null  $walletUserId
     * @return array<int, array<string, mixed>>
     */
    public function listDetails(int $walletId, ?bool $isPersonal, ?int $walletUserId = null): array;

    /**
     * 取得帳本成員清單。
     *
     * @param  int  $walletId
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array;

    /**
     * 取得單筆帳本明細。
     *
     * @param  int  $walletId
     * @param  int  $detailId
     * @return array<string, mixed>|null
     */
    public function findDetail(int $walletId, int $detailId): ?array;

    /**
     * 取得帳本資訊。
     *
     * @param  int  $walletId
     * @return array<string, mixed>|null
     */
    public function findWallet(int $walletId): ?array;

    /**
     * 更新帳本明細。
     *
     * @param  int  $walletId
     * @param  int  $detailId
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    public function updateDetail(int $walletId, int $detailId, array $attributes): void;

    /**
     * 刪除帳本明細。
     *
     * @param  int  $walletId
     * @param  int  $detailId
     * @return void
     */
    public function deleteDetail(int $walletId, int $detailId): void;

    /**
     * 將指定明細結帳。
     *
     * @param  int  $walletId
     * @param  array<int, int>  $detailIds
     * @param  int  $walletUserId
     * @return void
     */
    public function checkout(int $walletId, array $detailIds, int $walletUserId): void;

    /**
     * 依結帳時間取消結帳。
     *
     * @param  int  $walletId
     * @param  string  $checkoutAt
     * @param  int  $walletUserId
     * @return void
     */
    public function uncheckout(int $walletId, string $checkoutAt, int $walletUserId): void;
}
