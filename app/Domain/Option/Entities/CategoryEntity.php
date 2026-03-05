<?php

declare(strict_types=1);

namespace App\Domain\Option\Entities;

use App\Domain\Wallet\Entities\WalletEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'categories';

    /** @var array<int, string> */
    protected $fillable = [
        'parent_id',
        'wallet_id',
        'name',
        'icon',
    ];

    /**
     * 子分類。
     *
     * @return HasMany<CategoryEntity, CategoryEntity>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 父分類。
     *
     * @return BelongsTo<CategoryEntity, CategoryEntity>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 所屬帳本。
     *
     * @return BelongsTo<WalletEntity, CategoryEntity>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WalletEntity::class, 'wallet_id', 'id');
    }
}
