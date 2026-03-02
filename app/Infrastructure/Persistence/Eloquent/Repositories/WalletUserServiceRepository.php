<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Repositories\WalletUserServiceRepositoryInterface;

class WalletUserServiceRepository implements WalletUserServiceRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array
    {
        return WalletEntity::query()->where('code', $code)->first(['id', 'code'])?->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->get(['id', 'name', 'user_id', 'is_admin', 'notify_enable'])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'user_id' => $item->user_id ? (int) $item->user_id : null,
                'is_admin' => (bool) $item->is_admin,
                'notify_enable' => (bool) ($item->notify_enable ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWalletUser(int $walletUserId, array $attributes): void
    {
        WalletUserEntity::query()->where('id', $walletUserId)->update($attributes);
    }

    public function deleteWalletUser(int $walletId, int $walletUserId): void
    {
        WalletUserEntity::query()->where('wallet_id', $walletId)->where('id', $walletUserId)->delete();
    }
}
