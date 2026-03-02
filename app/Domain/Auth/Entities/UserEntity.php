<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entities;

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
}
