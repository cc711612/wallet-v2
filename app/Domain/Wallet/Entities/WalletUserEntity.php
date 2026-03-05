<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Device\Entities\DeviceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletUserEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'wallet_users';

    /** @var array<int, string> */
    protected $fillable = [
        'wallet_id',
        'user_id',
        'name',
        'token',
        'is_admin',
        'notify_enable',
        'agent',
        'ip',
    ];

    /** @var array<int, string> */
    protected $casts = [
        'is_admin' => 'integer',
        'notify_enable' => 'integer',
    ];

    /**
     * @return BelongsTo<WalletEntity, WalletUserEntity>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WalletEntity::class, 'wallet_id');
    }

    /**
     * 舊版相容命名：所屬帳本。
     *
     * @return BelongsTo<WalletEntity, WalletUserEntity>
     */
    public function wallets(): BelongsTo
    {
        return $this->belongsTo(WalletEntity::class, 'wallet_id', 'id');
    }

    /**
     * 所屬使用者。
     *
     * @return BelongsTo<UserEntity, WalletUserEntity>
     */
    public function users(): BelongsTo
    {
        return $this->belongsTo(UserEntity::class, 'user_id', 'id');
    }

    /**
     * 帳本成員可用裝置。
     *
     * @return HasMany<DeviceEntity, WalletUserEntity>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(DeviceEntity::class, 'wallet_user_id', 'id')
            ->where('expired_at', '>', now());
    }

    /**
     * 參與分攤的明細。
     *
     * @return BelongsToMany<WalletDetailEntity, WalletUserEntity>
     */
    public function wallet_details(): BelongsToMany
    {
        return $this->belongsToMany(
            WalletDetailEntity::class,
            'wallet_detail_wallet_user',
            'wallet_user_id',
            'wallet_detail_id'
        );
    }

    /**
     * 建立的明細。
     *
     * @return HasMany<WalletDetailEntity, WalletUserEntity>
     */
    public function created_wallet_details(): HasMany
    {
        return $this->hasMany(WalletDetailEntity::class, 'created_by', 'id');
    }

    /**
     * 付款人明細。
     *
     * @return HasMany<WalletDetailEntity, WalletUserEntity>
     */
    public function payment_wallet_details(): HasMany
    {
        return $this->hasMany(WalletDetailEntity::class, 'payment_wallet_user_id', 'id');
    }
}
