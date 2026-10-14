<?php

declare(strict_types=1);

namespace App\Domain\ExchangeRate\Services;

use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

            $this->saveRates($checkDate, $baseUnit, $rates);
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

        /** @var array<string, float|int|string> $rates */
        $rates = (array) Arr::get($historyResult, 'conversion_rates', []);
        $this->saveRates($date, $currency, $rates);
    }

    /**
     * 逐筆寫入匯率；單一幣別寫入失敗只記 log，不中斷其他幣別。
     * 全部幣別都失敗（例如資料庫無法連線）時拋出最後一個例外，避免靜默失敗。
     *
     * @param  array<string, float|int|string>  $rates
     *
     * @throws QueryException
     */
    private function saveRates(string $date, string $fromCurrency, array $rates): void
    {
        $lastException = null;
        $failedCount = 0;

        foreach ($rates as $toCurrency => $rate) {
            try {
                $this->exchangeRateRepository->upsertByDateAndCurrency([
                    'date' => $date,
                    'from_currency' => $fromCurrency,
                    'to_currency' => (string) $toCurrency,
                ], [
                    'date' => $date,
                    'from_currency' => $fromCurrency,
                    'to_currency' => (string) $toCurrency,
                    'rate' => (float) $rate,
                ]);
            } catch (QueryException $exception) {
                $lastException = $exception;
                $failedCount++;
                Log::warning('ExchangeRateService failed to save rate', [
                    'date' => $date,
                    'from_currency' => $fromCurrency,
                    'to_currency' => (string) $toCurrency,
                    'rate' => $rate,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($lastException !== null && $failedCount === count($rates)) {
            throw $lastException;
        }
    }
}
