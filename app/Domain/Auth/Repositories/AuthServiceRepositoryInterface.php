<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

interface AuthServiceRepositoryInterface
{
    /**
     * @param  string  $account
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array;

    /**
     * @param  int  $userId
     * @param  string  $token
     * @return void
     * Rotate member token after login/logout.
     */
    public function updateUserToken(int $userId, string $token): void;

    /**
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return void
     * Persist latest client metadata.
     */
    public function updateUserAgentIp(int $userId, string $agent, string $ip): void;

    /**
     * @param  int  $userId
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array;

    /**
     * @param  int  $walletId
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array;

    /**
     * @param  string  $account
     * @return bool
     * Check whether account already exists.
     */
    public function accountExists(string $account): bool;

    /**
     * @param  array<string, mixed>  $attributes
     * @return int
     */
    public function createUser(array $attributes): int;

    /**
     * @param  int  $walletUserId
     * @return array<string, mixed>|null
     */
    public function findWalletUserById(int $walletUserId): ?array;

    /**
     * @param  int  $walletId
     * @param  int  $userId
     * @return bool
     * Check whether a user already binds to the wallet.
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool;

    /**
     * @param  int  $walletUserId
     * @param  int  $userId
     * @param  string  $agent
     * @param  string  $ip
     * @return bool
     * Bind a wallet user row to real user when invite bind login succeeds.
     */
    public function bindWalletUser(int $walletUserId, int $userId, string $agent, string $ip): bool;
}
