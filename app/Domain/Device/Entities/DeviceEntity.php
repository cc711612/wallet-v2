<?php

declare(strict_types=1);

namespace App\Domain\Device\Entities;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'devices';

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'wallet_user_id',
        'platform',
        'device_name',
        'device_type',
        'fcm_token',
        'expired_at',
    ];

    /**
     * 所屬使用者。
     *
     * @return BelongsTo<UserEntity, DeviceEntity>
     */
    public function users(): BelongsTo
    {
        return $this->belongsTo(UserEntity::class);
    }

    /**
     * 所屬帳本成員。
     *
     * @return BelongsTo<WalletUserEntity, DeviceEntity>
     */
    public function walletUsers(): BelongsTo
    {
        return $this->belongsTo(WalletUserEntity::class, 'wallet_user_id');
    }
}
