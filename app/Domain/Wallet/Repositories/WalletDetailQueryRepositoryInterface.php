<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletDetailQueryRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetails(int $walletId, ?bool $isPersonal, ?int $walletUserId = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findDetail(int $walletId, int $detailId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWallet(int $walletId): ?array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDetail(int $walletId, int $detailId, array $attributes): void;

    public function deleteDetail(int $walletId, int $detailId): void;

    /**
     * @param  array<int, int>  $detailIds
     */
    public function checkout(int $walletId, array $detailIds): void;

    public function uncheckout(int $walletId, string $checkoutAt): void;
}
