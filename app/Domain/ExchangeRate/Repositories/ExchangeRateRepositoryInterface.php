<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Repositories;

interface ExchangeRateRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function upsertByDateAndCurrency(array $keys, array $values): void;

    public function existsByCurrencyAndDate(string $fromCurrency, string $date): bool;
}
