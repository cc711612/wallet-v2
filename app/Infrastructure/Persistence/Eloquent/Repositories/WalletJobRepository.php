<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use Illuminate\Support\Facades\DB;

class WalletJobRepository implements WalletJobRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findUserById(int $userId): ?array
    {
        return UserEntity::query()->find($userId)?->toArray();
    }

    /**
     * @return array<int, int>
     */
    public function listWalletUserIds(int $walletId): array
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function listSelectAllDetailIds(int $walletId): array
    {
        return WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->where('select_all', 1)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function syncDetailUsersWithoutDetaching(int $detailId, array $walletUserIds): void
    {
        $detail = WalletDetailEntity::query()->find($detailId);
        if ($detail === null) {
            return;
        }

        $detail->users()->syncWithoutDetaching($walletUserIds);
    }

    /**
     * @param  array<int, int>  $detailIds
     * @param  array<int, int>  $walletUserIds
     */
    public function syncDetailUsersWithoutDetachingBatch(array $detailIds, array $walletUserIds): void
    {
        if ($detailIds === [] || $walletUserIds === []) {
            return;
        }

        $rows = [];
        foreach ($detailIds as $detailId) {
            foreach ($walletUserIds as $walletUserId) {
                $rows[] = [
                    'wallet_detail_id' => $detailId,
                    'wallet_user_id' => $walletUserId,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('wallet_detail_wallet_user')->insertOrIgnore($chunk);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletUserByWalletAndUser(int $walletId, int $userId): ?array
    {
        $walletUser = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('user_id', $userId)
            ->first();

        return $walletUser?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAdminWalletUserByWalletId(int $walletId): ?array
    {
        $walletUser = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('is_admin', 1)
            ->first();

        return $walletUser?->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletDetail(array $attributes): int
    {
        $detail = WalletDetailEntity::query()->create($attributes);

        return (int) $detail->id;
    }

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function syncDetailUsers(int $detailId, array $walletUserIds): void
    {
        $detail = WalletDetailEntity::query()->find($detailId);
        if ($detail === null) {
            return;
        }

        $detail->users()->sync($walletUserIds);
    }
}
