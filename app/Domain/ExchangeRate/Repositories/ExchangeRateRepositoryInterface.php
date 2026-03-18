<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Repositories;

interface ExchangeRateRepositoryInterface
{
    /**
     * 寫入或更新指定幣別日期匯率。
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function upsertByDateAndCurrency(array $keys, array $values): void;

    /**
     * 檢查指定幣別與日期的匯率是否存在。
     */
    public function existsByCurrencyAndDate(string $fromCurrency, string $date): bool;
}
