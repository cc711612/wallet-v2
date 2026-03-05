<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

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
        'splits' => 'array',
        'select_all' => 'integer',
        'is_personal' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(WalletUserEntity::class, 'wallet_detail_wallet_user', 'wallet_detail_id', 'wallet_user_id');
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

    public function splitRows(): HasMany
    {
        return $this->hasMany(WalletDetailSplitEntity::class, 'wallet_detail_id', 'id');
    }
}
