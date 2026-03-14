<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletServiceRepositoryInterface
{
    /**
     * 取得帳本列表與分頁資訊。
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWallets(array $filters): array;

    /**
     * 建立帳本。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createWallet(array $attributes): array;

    /**
     * 建立帳本擁有者。
     *
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    public function createWalletOwner(array $attributes): void;

    /**
     * 更新帳本資料。
     *
     * @param  int  $walletId
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    public function updateWallet(int $walletId, array $attributes): void;

    /**
     * 刪除帳本。
     *
     * @param  int  $walletId
     * @return int
     */
    public function deleteWallet(int $walletId): int;

    /**
     * 依帳本驗證碼取得帳本資料。
     *
     * @param  string  $code
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array;

    /**
     * 依帳本與名稱取得帳本成員。
     *
     * @param  int  $walletId
     * @param  string  $name
     * @return array<string, mixed>|null
     */
    public function findWalletUserByName(int $walletId, string $name): ?array;

    /**
     * 檢查使用者是否已在帳本中綁定。
     *
     * @param  int  $walletId
     * @param  int  $userId
     * @return bool
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool;

    /**
     * 綁定帳本成員到使用者。
     *
     * @param  int  $walletUserId
     * @param  int  $userId
     * @return bool
     */
    public function bindWalletUser(int $walletUserId, int $userId): bool;

    /**
     * 檢查帳本是否由指定使用者擁有。
     *
     * @param  int  $walletId
     * @param  int  $userId
     * @return bool
     */
    public function existsWalletOwnedByUser(int $walletId, int $userId): bool;

    /**
     * 更新帳本成員時間戳。
     *
     * @param  int  $walletId
     * @param  string  $name
     * @return int
     */
    public function touchWalletUserByName(int $walletId, string $name): int;

    /**
     * 取得帳本收支總計。
     *
     * @param  int  $walletId
     * @return array<string, float>
     */
    public function walletDetailTotals(int $walletId): array;

    /**
     * 取得帳本公費收支總計。
     *
     * @param  int  $walletId
     * @return array<string, float>
     */
    public function walletPublicDetailTotals(int $walletId): array;

    /**
     * 取得帳本成員列表。
     *
     * @param  int  $walletId
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsersByWalletId(int $walletId): array;
}
