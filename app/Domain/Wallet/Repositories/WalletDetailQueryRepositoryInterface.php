<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletDetailQueryRepositoryInterface
{
    /**
     * 取得帳本明細清單。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDetails(int $walletId, ?bool $isPersonal, ?int $walletUserId = null): array;

    /**
     * 取得帳本成員清單。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array;

    /**
     * 取得單筆帳本明細。
     *
     * @return array<string, mixed>|null
     */
    public function findDetail(int $walletId, int $detailId): ?array;

    /**
     * 取得帳本資訊。
     *
     * @return array<string, mixed>|null
     */
    public function findWallet(int $walletId): ?array;

    /**
     * 更新帳本明細。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDetail(int $walletId, int $detailId, array $attributes): void;

    /**
     * 先清空再同步明細分攤成員。
     *
     * @param  array<int, int>  $userIds
     */
    public function replaceDetailUsers(int $walletId, int $detailId, array $userIds): void;

    /**
     * 刪除帳本明細。
     */
    public function deleteDetail(int $walletId, int $detailId): void;

    /**
     * 將指定明細結帳。
     *
     * @param  array<int, int>  $detailIds
     */
    public function checkout(int $walletId, array $detailIds, int $walletUserId): void;

    /**
     * 依結帳時間取消結帳。
     */
    public function uncheckout(int $walletId, string $checkoutAt, int $walletUserId): void;
}
