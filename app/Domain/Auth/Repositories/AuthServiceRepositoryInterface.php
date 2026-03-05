<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

interface AuthServiceRepositoryInterface
{
    /**
     * 依帳號取得使用者資料。
     *
     * @param  string  $account
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array;

    /**
     * 依使用者 ID 取得使用者資料。
     *
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findUserById(int $userId): ?array;

    /**
     * 更新登入 token（member_token）。
     *
     * @param  int  $userId
     * @param  string  $token
     * @return void
     */
    public function updateUserToken(int $userId, string $token): void;

    /**
     * 更新使用者登入裝置資訊。
     *
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return void
     */
    public function updateUserAgentIp(int $userId, string $agent, string $ip): void;

    /**
     * 取得使用者最新一筆帳本成員資料。
     *
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array;

    /**
     * 取得指定帳本摘要資料。
     *
     * @param  int  $walletId
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array;

    /**
     * 取得使用者擁有的最新帳本。
     *
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findLatestOwnedWalletByUserId(int $userId): ?array;

    /**
     * 取得使用者可見的帳本成員清單。
     *
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsersByUserId(int $userId): array;

    /**
     * 取得使用者有效裝置清單。
     *
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listActiveDevicesByUserId(int $userId): array;

    /**
     * 取得使用者通知設定清單。
     *
     * @param  int  $userId
     * @return array<int, array<string, mixed>>
     */
    public function listNotifiesByUserId(int $userId): array;

    /**
     * 檢查帳號是否已存在。
     *
     * @param  string  $account
     * @return bool
     */
    public function accountExists(string $account): bool;

    /**
     * 建立使用者資料。
     *
     * @param  array<string, mixed>  $attributes
     * @return int
     */
    public function createUser(array $attributes): int;

    /**
     * 依帳本成員 ID 取得資料。
     *
     * @param  int  $walletUserId
     * @return array<string, mixed>|null
     */
    public function findWalletUserById(int $walletUserId): ?array;

    /**
     * 檢查使用者是否已綁定到指定帳本。
     *
     * @param  int  $walletId
     * @param  int  $userId
     * @return bool
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool;

    /**
     * 將邀請中的帳本成員綁定到正式使用者。
     *
     * @param  int  $walletUserId
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return bool
     */
    public function bindWalletUser(int $walletUserId, int $userId, string $agent, string $ip): bool;
}
