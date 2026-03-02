<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\ExchangeRate\Entities\ExchangeRateEntity;
use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function upsertByDateAndCurrency(array $keys, array $values): void
    {
        ExchangeRateEntity::query()->updateOrCreate($keys, $values);
    }

    public function existsByCurrencyAndDate(string $fromCurrency, string $date): bool
    {
        return ExchangeRateEntity::query()
            ->where('from_currency', $fromCurrency)
            ->whereDate('date', $date)
            ->exists();
    }
}
