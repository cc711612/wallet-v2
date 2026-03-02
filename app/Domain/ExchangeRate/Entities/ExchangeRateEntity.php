<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Entities;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateEntity extends Model
{
    protected $table = 'exchange_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'date',
    ];
}
