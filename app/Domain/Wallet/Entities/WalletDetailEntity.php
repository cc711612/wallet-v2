<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use App\Domain\ExchangeRate\Entities\ExchangeRateEntity;
use App\Domain\Option\Entities\CategoryEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletDetailEntity extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $touches = ['wallet'];

    /** @var string */
    protected $table = 'wallet_details';

    /** @var array<int, string> */
    protected $fillable = [
        'wallet_id',
        'category_id',
        'type',
        'payment_wallet_user_id',
        'title',
        'symbol_operation_type_id',
        'select_all',
        'is_personal',
        'value',
        'unit',
        'rates',
        'date',
        'note',
        'splits',
        'checkout_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<int, string> */
    protected $casts = [
        'splits' => 'json',
        'select_all' => 'boolean',
        'is_personal' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(WalletUserEntity::class, 'wallet_detail_wallet_user', 'wallet_detail_id', 'wallet_user_id');
    }

    /**
     * 舊版相容命名：分攤成員。
     *
     * @return BelongsToMany<WalletUserEntity, WalletDetailEntity>
     */
    public function wallet_users(): BelongsToMany
    {
        return $this->belongsToMany(
            WalletUserEntity::class,
            'wallet_detail_wallet_user',
            'wallet_detail_id',
            'wallet_user_id'
        );
    }

    /**
     * @return BelongsTo<WalletEntity, WalletDetailEntity>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WalletEntity::class, 'wallet_id');
    }

    /**
     * @return BelongsTo<CategoryEntity, WalletDetailEntity>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryEntity::class, 'category_id');
    }

    /**
     * 付款人。
     *
     * @return BelongsTo<WalletUserEntity, WalletDetailEntity>
     */
    public function payment_user(): BelongsTo
    {
        return $this->belongsTo(WalletUserEntity::class, 'payment_wallet_user_id', 'id');
    }

    /**
     * 建立者。
     *
     * @return BelongsTo<WalletUserEntity, WalletDetailEntity>
     */
    public function created_user(): BelongsTo
    {
        return $this->belongsTo(WalletUserEntity::class, 'created_by', 'id');
    }

    public function splitRows(): HasMany
    {
        return $this->hasMany(WalletDetailSplitEntity::class, 'wallet_detail_id', 'id');
    }

    /**
     * 舊版相容命名：明細拆分。
     *
     * @return HasMany<WalletDetailSplitEntity, WalletDetailEntity>
     */
    public function wallet_detail_splits(): HasMany
    {
        return $this->hasMany(WalletDetailSplitEntity::class, 'wallet_detail_id', 'id');
    }

    /**
     * 明細日期對應匯率清單。
     *
     * @return \\Illuminate\\Database\\Eloquent\\Collection<int, ExchangeRateEntity>
     */
    public function exchange_rates()
    {
        if ($this->created_at === null) {
            return ExchangeRateEntity::query()->whereRaw('1 = 0')->get();
        }

        return ExchangeRateEntity::query()
            ->whereDate('date', '=', $this->created_at->format('Y-m-d'))
            ->get();
    }
}
