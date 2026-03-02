<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Repositories;

interface WalletMemberTokenRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByWalletUserId(int $walletUserId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByUserId(int $userId): array;
}
