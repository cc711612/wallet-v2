<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Entities;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateEntity extends Model
{
    protected $table = 'exchange_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'date',
    ];

    /**
     * 舊版相容命名：匯率對應的明細幣別。
     *
     * @return BelongsTo<WalletDetailEntity, ExchangeRateEntity>
     */
    public function wallet_details(): BelongsTo
    {
        return $this->belongsTo(WalletDetailEntity::class, 'to_currency', 'unit');
    }
}
