<?php

declare(strict_types=1);

namespace App\Domain\Option\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryEntity extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'categories';

    /** @var array<int, string> */
    protected $fillable = [
        'parent_id',
        'wallet_id',
        'name',
        'icon',
    ];
}
