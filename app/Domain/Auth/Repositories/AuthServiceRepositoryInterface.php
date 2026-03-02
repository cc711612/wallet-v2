<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

interface AuthServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findUserByAccount(string $account): ?array;

    public function updateUserToken(int $userId, string $token): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestWalletUserByUserId(int $userId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletById(int $walletId): ?array;

    public function accountExists(string $account): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): int;
}
