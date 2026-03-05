<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletDetailSplitEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'wallet_detail_splits';

    /** @var array<int, string> */
    protected $fillable = [
        'wallet_detail_id',
        'wallet_user_id',
        'unit',
        'value',
    ];

    /**
     * 關聯帳本成員。
     *
     * @return BelongsTo<WalletUserEntity, WalletDetailSplitEntity>
     */
    public function wallet_users(): BelongsTo
    {
        return $this->belongsTo(WalletUserEntity::class, 'wallet_user_id', 'id');
    }

    /**
     * 關聯帳本明細。
     *
     * @return BelongsTo<WalletDetailEntity, WalletDetailSplitEntity>
     */
    public function wallet_details(): BelongsTo
    {
        return $this->belongsTo(WalletDetailEntity::class, 'wallet_detail_id', 'id');
    }
}
