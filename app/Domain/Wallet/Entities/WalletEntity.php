<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use App\Domain\Auth\Entities\UserEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
