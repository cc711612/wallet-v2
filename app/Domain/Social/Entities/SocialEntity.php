<?php

declare(strict_types=1);

namespace App\Domain\Social\Entities;

use App\Domain\Auth\Entities\UserEntity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'socials';

    /** @var array<int, string> */
    protected $fillable = [
        'wallet_id',
        'name',
        'email',
        'social_type',
        'social_type_value',
        'image',
        'token',
    ];

    /**
     * 綁定此第三方帳號的使用者。
     *
     * @return BelongsToMany<UserEntity, SocialEntity>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(UserEntity::class, 'user_social', 'social_id', 'user_id');
    }
}
