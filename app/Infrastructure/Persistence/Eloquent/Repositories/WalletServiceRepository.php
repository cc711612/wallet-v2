<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Domain\Wallet\Repositories\WalletServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class WalletServiceRepository implements WalletServiceRepositoryInterface
{
    /**
     * 取得帳本列表與分頁資訊。
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWallets(array $filters): array
    {
        $perPage = max(1, (int) ($filters['per_page'] ?? $filters['page_count'] ?? 50));
        $userId = (int) data_get($filters, 'user.id', data_get($filters, 'users.id', 0));
        $status = data_get($filters, 'wallets.status', $filters['status'] ?? null);
        $isGuest = data_get($filters, 'wallets.is_guest', $filters['is_guest'] ?? null);

        $query = WalletEntity::query()
            ->with(['user:id,name'])
            ->select(['id', 'user_id', 'title', 'code', 'unit', 'mode', 'properties', 'status', 'updated_at', 'created_at']);

        if ($userId > 0) {
            $guestWalletIds = WalletUserEntity::query()
                ->where('user_id', $userId)
                ->where('is_admin', 0)
                ->pluck('wallet_id')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            if ($isGuest !== null) {
                if ((int) $isGuest === 0) {
                    $query->where('user_id', $userId);
                } else {
                    $query->whereIn('id', $guestWalletIds ?: [0]);
                }
            } else {
                $query->where(function (Builder $builder) use ($guestWalletIds, $userId): void {
                    $builder->whereIn('id', $guestWalletIds ?: [0])
                        ->orWhere('user_id', $userId);
                });
            }
        }

        if (is_numeric($status)) {
            $query->where('status', (int) $status);
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);
        $wallets = collect($paginator->items())
            ->map(static function (WalletEntity $wallet): array {
                $properties = is_array($wallet->properties) ? $wallet->properties : [];
                if (! array_key_exists('unitConfigurable', $properties)) {
                    $properties['unitConfigurable'] = false;
                }

                if (! array_key_exists('decimalPlaces', $properties)) {
                    $properties['decimalPlaces'] = 0;
                }

                return [
                    'id' => (int) $wallet->id,
                    'title' => (string) $wallet->title,
                    'code' => (string) $wallet->code,
                    'status' => (int) $wallet->status,
                    'unit' => (string) ($wallet->unit ?? 'TWD'),
                    'mode' => (string) ($wallet->mode ?? 'multi'),
                    'properties' => $properties,
                    'user' => [
                        'id' => $wallet->user ? (int) $wallet->user->id : null,
                        'name' => $wallet->user ? (string) $wallet->user->name : null,
                    ],
                    'updated_at' => $wallet->updated_at?->format('Y-m-d H:i:s') ?? '',
                    'created_at' => $wallet->created_at?->format('Y-m-d H:i:s') ?? '',
                ];
            })
            ->values()
            ->all();

        return [
            'paginate' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'wallets' => $wallets,
        ];
    }

    /**
     * 建立帳本。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createWallet(array $attributes): array
    {
        $wallet = WalletEntity::query()->create($attributes);

        return $wallet->toArray();
    }

    /**
     * 建立帳本擁有者。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWalletOwner(array $attributes): void
    {
        WalletUserEntity::query()->create($attributes);
    }

    /**
     * 更新帳本資料。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateWallet(int $walletId, array $attributes): void
    {
        WalletEntity::query()->where('id', $walletId)->update($attributes);
    }

    /**
     * 刪除帳本。
     */
    public function deleteWallet(int $walletId): int
    {
        return WalletEntity::query()->where('id', $walletId)->delete();
    }

    /**
     * 依帳本驗證碼取得帳本資料。
     *
     * @return array<string, mixed>|null
     */
    public function findWalletByCode(string $code): ?array
    {
        $wallet = WalletEntity::query()->where('code', $code)->first(['id', 'code']);

        return $wallet?->toArray();
    }

    /**
     * 依帳本與名稱取得帳本成員。
     *
     * @return array<string, mixed>|null
     */
    public function findWalletUserByName(int $walletId, string $name): ?array
    {
        $walletUser = WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('name', $name)
            ->first(['id', 'wallet_id', 'user_id']);

        return $walletUser?->toArray();
    }

    /**
     * 檢查使用者是否已在帳本中綁定。
     */
    public function walletUserExistsByWalletAndUser(int $walletId, int $userId): bool
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 綁定帳本成員到使用者。
     */
    public function bindWalletUser(int $walletUserId, int $userId): bool
    {
        $updated = WalletUserEntity::query()
            ->where('id', $walletUserId)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);

        return $updated > 0;
    }

    public function existsWalletOwnedByUser(int $walletId, int $userId): bool
    {
        return WalletEntity::query()
            ->where('id', $walletId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 更新帳本成員時間戳。
     */
    public function touchWalletUserByName(int $walletId, string $name): int
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->where('name', $name)
            ->update(['updated_at' => now()]);
    }

    /**
     * 取得帳本收支總計。
     *
     * @return array<string, float>
     */
    public function walletDetailTotals(int $walletId): array
    {
        $details = WalletDetailEntity::query()->where('wallet_id', $walletId)->get(['symbol_operation_type_id', 'value']);

        return [
            'income' => (float) $details->where('symbol_operation_type_id', 1)->sum('value'),
            'expenses' => (float) $details->where('symbol_operation_type_id', 2)->sum('value'),
        ];
    }

    /**
     * 取得帳本公費收支總計。
     *
     * @return array<string, float>
     */
    public function walletPublicDetailTotals(int $walletId): array
    {
        $details = WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->where('type', WalletDetailType::PUBLIC_EXPENSE->value)
            ->get(['symbol_operation_type_id', 'value']);

        return [
            'income' => (float) $details->where('symbol_operation_type_id', 1)->sum('value'),
            'expenses' => (float) $details->where('symbol_operation_type_id', 2)->sum('value'),
        ];
    }

    /**
     * 取得帳本成員列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsersByWalletId(int $walletId): array
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
            ])
            ->all();
    }
}
