<?php

declare(strict_types=1);

namespace App\Domain\Social\Entities;

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
}
