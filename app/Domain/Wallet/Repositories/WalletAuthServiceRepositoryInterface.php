<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletAuthServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByName(int $walletId, string $name): ?array;

    /**
     * @return array<string, mixed>
     */
    public function updateWalletUserToken(int $walletUserId, string $token): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByToken(int $walletId, string $token): ?array;

    /**
     * 檢查帳本內是否已有相同名稱成員。
     *
     * @param  int  $walletId
     * @param  string  $name
     * @return bool
     */
    public function walletUserNameExists(int $walletId, string $name): bool;

    /**
     * 建立單一帳本成員。
     *
     * @param  array<string, mixed>  $attributes
     * @return int
     */
    public function createWalletUser(array $attributes): int;

    /**
     * 批次建立帳本成員。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return void
     */
    public function batchCreateWalletUsers(array $rows): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDevicesByWalletUserId(int $walletUserId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotifiesByWalletUserId(int $walletUserId): array;
}
