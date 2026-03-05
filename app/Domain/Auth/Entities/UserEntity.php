<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entities;

use App\Domain\Device\Entities\DeviceEntity;
use App\Domain\Social\Entities\SocialEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'users';

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'account',
        'image',
        'verified_at',
        'password',
        'token',
        'notify_token',
        'agent',
        'ip',
    ];

    /** @var array<int, string> */
    protected $hidden = ['password', 'token'];

    /**
     * 使用者建立的帳本。
     *
     * @return HasMany<WalletEntity, UserEntity>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(WalletEntity::class, 'user_id', 'id');
    }

    /**
     * 使用者綁定的第三方帳號。
     *
     * @return BelongsToMany<SocialEntity, UserEntity>
     */
    public function socials(): BelongsToMany
    {
        return $this->belongsToMany(SocialEntity::class, 'user_social', 'user_id', 'social_id');
    }

    /**
     * 使用者在各帳本的成員資料。
     *
     * @return HasMany<WalletUserEntity, UserEntity>
     */
    public function wallet_users(): HasMany
    {
        return $this->hasMany(WalletUserEntity::class, 'user_id', 'id');
    }

    /**
     * 使用者有效裝置。
     *
     * @return HasMany<DeviceEntity, UserEntity>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(DeviceEntity::class, 'user_id', 'id')
            ->where('expired_at', '>', now());
    }
}
