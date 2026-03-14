<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Services;

use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * 建立匯率服務並注入儲存庫。
     *
     * @return void
     */
    public function __construct(private ExchangeRateRepositoryInterface $exchangeRateRepository) {}

    /**
     * 同步即時匯率資料。
     */
    public function setExchangeRate(): void
    {
        $domain = (string) config('services.exchangeRate.domain', '');
        if ($domain === '') {
            return;
        }

        /** @var array<int, string> $baseUnits */
        $baseUnits = ['TWD', 'USD'];

        foreach ($baseUnits as $baseUnit) {
            /** @var array<string, mixed>|mixed $result */
            $result = Http::timeout(15)->get($domain.$baseUnit)->json();
            if (! is_array($result)) {
                continue;
            }

            $checkDate = (string) Arr::get($result, 'date', Carbon::now()->toDateString());
            /** @var array<string, float|int|string> $rates */
            $rates = (array) Arr::get($result, 'rates', []);

            foreach ($rates as $unit => $rate) {
                $this->exchangeRateRepository->upsertByDateAndCurrency([
                    'date' => $checkDate,
                    'from_currency' => $baseUnit,
                    'to_currency' => (string) $unit,
                ], [
                    'date' => $checkDate,
                    'from_currency' => $baseUnit,
                    'to_currency' => (string) $unit,
                    'rate' => (float) $rate,
                ]);
            }
        }
    }

    /**
     * 檢查指定幣別日期匯率是否已存在。
     */
    public function isExistExchangeRateByCurrencyAndDate(string $fromCurrency, string $date): bool
    {
        return $this->exchangeRateRepository->existsByCurrencyAndDate($fromCurrency, $date);
    }

    /**
     * 取得歷史匯率資料。
     *
     * @return array<string, mixed>
     */
    public function getHistoryExchangeRateByCurrencyAndDate(string $fromCurrency, string $date): array
    {
        $baseUrl = (string) config('services.exchangeRate.history', '');
        if ($baseUrl === '') {
            return [];
        }

        $url = str_replace(
            ['{APIKEY}', '{UNIT}', '{DATESTRING}'],
            [
                (string) config('services.exchangeRate.apiKey', ''),
                $fromCurrency,
                $date,
            ],
            $baseUrl,
        );

        $result = Http::timeout(20)->get($url)->json();

        return is_array($result) ? $result : [];
    }

    /**
     * 依歷史匯率回應寫入資料庫。
     *
     * @param  array<string, mixed>  $historyResult
     */
    public function updateHistoryByHistoryResult(array $historyResult): void
    {
        if ((string) Arr::get($historyResult, 'result', '') !== 'success') {
            return;
        }

        $currency = (string) Arr::get($historyResult, 'base_code', '');
        if ($currency === '') {
            return;
        }

        $date = Carbon::create(
            (int) Arr::get($historyResult, 'year', 1970),
            (int) Arr::get($historyResult, 'month', 1),
            (int) Arr::get($historyResult, 'day', 1),
        )->format('Y-m-d');

        $rates = (array) Arr::get($historyResult, 'conversion_rates', []);
        foreach ($rates as $toCurrency => $rate) {
            $this->exchangeRateRepository->upsertByDateAndCurrency([
                'date' => $date,
                'from_currency' => $currency,
                'to_currency' => (string) $toCurrency,
            ], [
                'date' => $date,
                'from_currency' => $currency,
                'to_currency' => (string) $toCurrency,
                'rate' => (float) $rate,
            ]);
        }
    }
}
