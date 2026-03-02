<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletServiceRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWallets(array $filters): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createWallet(array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletOwner(array $attributes): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWallet(int $walletId, array $attributes): void;

    public function deleteWallet(int $walletId): int;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    public function touchWalletUserByName(int $walletId, string $name): int;

    /**
     * @return array<string, float>
     */
    public function walletDetailTotals(int $walletId): array;

    /**
     * @return array<string, float>
     */
    public function walletPublicDetailTotals(int $walletId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsersByWalletId(int $walletId): array;
}
