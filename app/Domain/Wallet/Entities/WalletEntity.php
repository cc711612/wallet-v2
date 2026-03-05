<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use App\Domain\Auth\Entities\UserEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'wallets';

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'code',
        'title',
        'unit',
        'status',
        'properties',
        'mode',
    ];

    /** @var array<int, string> */
    protected $casts = [
        'properties' => 'array',
        'status' => 'integer',
    ];

    /**
     * @return BelongsTo<UserEntity, WalletEntity>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEntity::class, 'user_id');
    }

    /**
     * 舊版相容命名：帳本建立者。
     *
     * @return BelongsTo<UserEntity, WalletEntity>
     */
    public function users(): BelongsTo
    {
        return $this->belongsTo(UserEntity::class, 'user_id', 'id');
    }

    /**
     * 帳本明細。
     *
     * @return HasMany<WalletDetailEntity, WalletEntity>
     */
    public function wallet_details(): HasMany
    {
        return $this->hasMany(WalletDetailEntity::class, 'wallet_id', 'id');
    }

    /**
     * 帳本成員。
     *
     * @return HasMany<WalletUserEntity, WalletEntity>
     */
    public function wallet_users(): HasMany
    {
        return $this->hasMany(WalletUserEntity::class, 'wallet_id', 'id');
    }

    /**
     * 帳本建立者（admin）。
     *
     * @return HasMany<WalletUserEntity, WalletEntity>
     */
    public function wallet_user_created(): HasMany
    {
        return $this->hasMany(WalletUserEntity::class, 'wallet_id', 'id')
            ->where('is_admin', 1);
    }
}
