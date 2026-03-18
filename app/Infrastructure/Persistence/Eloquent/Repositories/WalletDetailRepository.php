<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletDetailSplitEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WalletDetailRepository implements WalletDetailRepositoryInterface
{
    /**
     * 取得帳本公費餘額。
     */
    public function getWalletBalance(int $walletId): float
    {
        $increment = WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->where('type', WalletDetailType::PUBLIC_EXPENSE->value)
            ->where('symbol_operation_type_id', SymbolOperationType::INCREMENT->value)
            ->sum('value');

        $decrement = WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->where('type', WalletDetailType::PUBLIC_EXPENSE->value)
            ->where('symbol_operation_type_id', SymbolOperationType::DECREMENT->value)
            ->sum('value');

        return (float) ($increment - $decrement);
    }

    /**
     * 檢查分攤成員是否都在帳本中。
     *
     * @param  array<int, int>  $walletUserIds
     */
    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool
    {
        if ($walletUserIds === []) {
            return false;
        }

        $count = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->whereIn('id', $walletUserIds)
            ->count();

        return $count === count($walletUserIds);
    }

    /**
     * 建立帳本明細與關聯資料。
     *
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array
    {
        /** @var Carbon $now */
        $now = now();

        /** @var array<string, mixed> $attributes */
        $attributes = $walletDetail->toPersistenceAttributes();

        /** @var int $detailId */
        $detailId = DB::transaction(function () use ($attributes, $now): int {
            /** @var array<int, int> $users */
            $users = array_values(array_unique(array_map('intval', $attributes['users'] ?? [])));

            if ((bool) ($attributes['select_all'] ?? false) === true) {
                $users = WalletUserEntity::query()
                    ->where('wallet_id', (int) $attributes['wallet_id'])
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();
            }

            /** @var array<int, array{user_id:int, value:float|int}> $splits */
            $splits = is_array($attributes['splits'] ?? null) ? $attributes['splits'] : [];

            $insertPayload = [
                'wallet_id' => $attributes['wallet_id'],
                'category_id' => $attributes['category_id'] ?? null,
                'type' => $attributes['type'],
                'payment_wallet_user_id' => $attributes['payment_wallet_user_id'] ?? null,
                'title' => $attributes['title'],
                'symbol_operation_type_id' => $attributes['symbol_operation_type_id'],
                'select_all' => (int) ($attributes['select_all'] ? 1 : 0),
                'is_personal' => (int) ($attributes['is_personal'] ? 1 : 0),
                'value' => $attributes['value'],
                'unit' => $attributes['unit'],
                'rates' => $attributes['rates'] ?? null,
                'date' => $attributes['date'],
                'note' => $attributes['note'] ?? null,
                'splits' => json_encode($splits, JSON_THROW_ON_ERROR),
                'created_by' => $attributes['created_by'] ?? null,
                'updated_by' => $attributes['updated_by'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            /** @var WalletDetailEntity $entity */
            $entity = WalletDetailEntity::query()->create($insertPayload);
            $id = (int) $entity->id;

            $entity->users()->sync($users);

            if ($splits !== []) {
                $splitRows = array_map(
                    static fn (array $split): array => [
                        'wallet_detail_id' => $id,
                        'wallet_user_id' => (int) $split['user_id'],
                        'unit' => (string) ($attributes['unit'] ?? 'TWD'),
                        'value' => (float) $split['value'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $splits
                );

                WalletDetailSplitEntity::query()->insert($splitRows);
            }

            return $id;
        });

        /** @var WalletDetailEntity|null $record */
        $record = WalletDetailEntity::query()->where('id', $detailId)->first([
            'id',
            'wallet_id',
            'title',
            'value',
            'type',
            'symbol_operation_type_id',
            'created_at',
        ]);

        /** @var array<string, mixed> $created */
        $created = $record ? $record->toArray() : [];

        return $created;
    }
}
