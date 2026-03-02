<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use Illuminate\Database\Eloquent\Model;
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
}
