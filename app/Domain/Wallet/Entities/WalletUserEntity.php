<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

use Illuminate\Database\Eloquent\Model;
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
}
