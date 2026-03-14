<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletUserServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array;

    /**
     * 更新帳本成員資料。
     *
     * @param  int  $walletUserId
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    public function updateWalletUser(int $walletUserId, array $attributes): void;

    /**
     * 刪除指定帳本成員。
     *
     * @param  int  $walletId
     * @param  int  $walletUserId
     * @return void
     */
    public function deleteWalletUser(int $walletId, int $walletUserId): void;
}
