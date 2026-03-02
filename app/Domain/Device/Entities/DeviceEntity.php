<?php

declare(strict_types=1);

namespace App\Domain\Device\Entities;

use Illuminate\Database\Eloquent\Model;
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
}
