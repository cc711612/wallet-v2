<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use Illuminate\Support\Carbon;

class WalletDetailQueryRepository implements WalletDetailQueryRepositoryInterface
{
    /**
     * 取得帳本明細清單。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDetails(int $walletId, ?bool $isPersonal, ?int $walletUserId = null): array
    {
        $query = WalletDetailEntity::query()->where('wallet_id', $walletId);

        if ($isPersonal !== null) {
            $query->where('is_personal', $isPersonal ? 1 : 0);
        }

        if ($isPersonal === true && ($walletUserId ?? 0) > 0) {
            $query->where('created_by', $walletUserId);
        }

        return $query
            ->with(['users:id', 'category'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get([
                'id',
                'category_id',
                'type',
                'title',
                'payment_wallet_user_id',
                'symbol_operation_type_id',
                'select_all',
                'is_personal',
                'value',
                'unit',
                'date',
                'note',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
                'checkout_at',
                'rates',
                'splits',
            ])
            ->map(static fn (WalletDetailEntity $item): array => [
                'id' => (int) $item->id,
                'type' => (int) $item->type,
                'title' => (string) $item->title,
                'payment_user_id' => $item->payment_wallet_user_id ? (int) $item->payment_wallet_user_id : null,
                'symbol_operation_type_id' => (int) $item->symbol_operation_type_id,
                'select_all' => (bool) $item->select_all,
                'is_personal' => (bool) $item->is_personal,
                'value' => $item->value,
                'unit' => (string) $item->unit,
                'date' => (string) $item->date,
                'note' => $item->note,
                'users' => $item->users->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all(),
                'checkout_by' => null,
                'created_by' => $item->created_by,
                'updated_by' => $item->updated_by,
                'created_at' => (string) $item->created_at,
                'updated_at' => (string) $item->updated_at,
                'checkout_at' => $item->checkout_at,
                'exchange_rates' => null,
                'rates' => $item->rates === null ? null : (float) $item->rates,
                'splits' => is_array($item->splits) ? $item->splits : [],
                'category' => $item->category ? [
                    'id' => (int) $item->category->id,
                    'parent_id' => $item->category->parent_id === null ? null : (int) $item->category->parent_id,
                    'wallet_id' => $item->category->wallet_id === null ? null : (int) $item->category->wallet_id,
                    'name' => (string) $item->category->name,
                    'icon' => $item->category->icon,
                    'created_at' => (string) $item->category->created_at,
                    'updated_at' => (string) $item->category->updated_at,
                    'deleted_at' => $item->category->deleted_at ? (string) $item->category->deleted_at : null,
                ] : null,
            ])
            ->all();
    }

    /**
     * 取得帳本成員清單。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array
    {
        return WalletUserEntity::query()
            ->where('wallet_id', $walletId)
            ->orderBy('id')
            ->get(['id', 'wallet_id', 'name', 'user_id', 'is_admin', 'notify_enable', 'created_at', 'updated_at'])
            ->map(static fn (WalletUserEntity $item): array => [
                'id' => (int) $item->id,
                'wallet_id' => (int) $item->wallet_id,
                'user_id' => $item->user_id ? (int) $item->user_id : null,
                'name' => (string) $item->name,
                'is_admin' => (bool) $item->is_admin,
                'notify_enable' => (bool) ($item->notify_enable ?? 0),
                'created_at' => Carbon::parse((string) $item->created_at, 'Asia/Taipei')->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => Carbon::parse((string) $item->updated_at, 'Asia/Taipei')->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ])
            ->all();
    }

    /**
     * 取得單筆帳本明細。
     *
     * @return array<string, mixed>|null
     */
    public function findDetail(int $walletId, int $detailId): ?array
    {
        $detail = WalletDetailEntity::query()
            ->with(['users:id'])
            ->where('wallet_id', $walletId)
            ->where('id', $detailId)
            ->first([
                'id',
                'type',
                'payment_wallet_user_id',
                'title',
                'symbol_operation_type_id',
                'select_all',
                'is_personal',
                'value',
                'rates',
                'splits',
                'note',
                'created_by',
                'updated_by',
                'updated_at',
                'checkout_at',
            ]);

        if ($detail === null) {
            return null;
        }

        return [
            'id' => (int) $detail->id,
            'type' => (int) $detail->type,
            'payment_wallet_user_id' => $detail->payment_wallet_user_id ? (int) $detail->payment_wallet_user_id : null,
            'title' => (string) $detail->title,
            'symbol_operation_type_id' => (int) $detail->symbol_operation_type_id,
            'select_all' => (bool) $detail->select_all,
            'is_personal' => (bool) $detail->is_personal,
            'value' => $detail->value,
            'rates' => $detail->rates === null ? null : (float) $detail->rates,
            'splits' => is_array($detail->splits) ? $detail->splits : [],
            'note' => $detail->note,
            'created_by' => $detail->created_by,
            'checkout_by' => null,
            'updated_by' => $detail->updated_by,
            'updated_at' => (string) $detail->updated_at,
            'checkout_at' => $detail->checkout_at,
            'users' => $detail->users->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * 取得帳本資訊。
     *
     * @return array<string, mixed>|null
     */
    public function findWallet(int $walletId): ?array
    {
        $wallet = WalletEntity::query()
            ->where('id', $walletId)
            ->first(['id', 'code', 'title', 'status', 'mode', 'unit', 'properties', 'created_at', 'updated_at']);

        if ($wallet === null) {
            return null;
        }

        return [
            'id' => (int) $wallet->id,
            'code' => (string) $wallet->code,
            'title' => (string) $wallet->title,
            'status' => (int) $wallet->status,
            'mode' => (string) $wallet->mode,
            'unit' => (string) $wallet->unit,
            'properties' => is_array($wallet->properties) ? $wallet->properties : [],
            'created_at' => Carbon::parse((string) $wallet->created_at, 'Asia/Taipei')->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => Carbon::parse((string) $wallet->updated_at, 'Asia/Taipei')->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * 更新帳本明細。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDetail(int $walletId, int $detailId, array $attributes): void
    {
        $attributes['updated_at'] = now();
        WalletDetailEntity::query()->where('wallet_id', $walletId)->where('id', $detailId)->update($attributes);
    }

    /**
     * 先清空再同步明細分攤成員。
     *
     * @param  array<int, int>  $userIds
     */
    public function replaceDetailUsers(int $walletId, int $detailId, array $userIds): void
    {
        $detail = WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->where('id', $detailId)
            ->first(['id']);

        if ($detail === null) {
            return;
        }

        $normalized = array_values(array_unique(array_map('intval', $userIds)));

        $detail->users()->detach();
        $detail->users()->sync($normalized);
    }

    /**
     * 刪除帳本明細。
     */
    public function deleteDetail(int $walletId, int $detailId): void
    {
        WalletDetailEntity::query()->where('wallet_id', $walletId)->where('id', $detailId)->delete();
    }

    /**
     * 將指定明細結帳。
     *
     * @param  array<int, int>  $detailIds
     */
    public function checkout(int $walletId, array $detailIds, int $walletUserId): void
    {
        if ($detailIds === []) {
            return;
        }

        WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->whereIn('id', $detailIds)
            ->update([
                'checkout_at' => now(),
                'checkout_by' => $walletUserId,
                'updated_at' => now(),
            ]);
    }

    /**
     * 依結帳時間取消結帳。
     */
    public function uncheckout(int $walletId, string $checkoutAt, int $walletUserId): void
    {
        WalletDetailEntity::query()
            ->where('wallet_id', $walletId)
            ->whereDate('checkout_at', $checkoutAt)
            ->update([
                'checkout_at' => null,
                'checkout_by' => null,
                'updated_by' => $walletUserId,
                'updated_at' => now(),
            ]);
    }
}
