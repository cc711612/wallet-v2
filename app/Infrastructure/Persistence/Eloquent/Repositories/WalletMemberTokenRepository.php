<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Repositories\WalletMemberTokenRepositoryInterface;

class WalletMemberTokenRepository implements WalletMemberTokenRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        /** @var WalletUserEntity|null $record */
        $record = WalletUserEntity::query()
            ->where('token', $token)
            ->first(['id', 'wallet_id', 'name', 'is_admin']);

        if (! $record instanceof WalletUserEntity) {
            return null;
        }

        /** @var array<string, mixed> $walletUser */
        $walletUser = $record->toArray();

        return $walletUser;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByWalletUserId(int $walletUserId): ?array
    {
        $record = WalletUserEntity::query()->where('id', $walletUserId)->first(['id', 'wallet_id', 'name', 'is_admin', 'user_id']);

        return $record?->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByUserId(int $userId): array
    {
        return WalletUserEntity::query()
            ->where('user_id', $userId)
            ->get(['id', 'wallet_id', 'name', 'is_admin', 'user_id'])
            ->map(static fn (WalletUserEntity $item): array => $item->toArray())
            ->all();
    }
}
