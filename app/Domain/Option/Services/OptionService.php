<?php

declare(strict_types=1);

namespace App\Domain\Option\Services;

use App\Domain\Option\Repositories\OptionServiceRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OptionService
{
    /** @var array<int, string> */
    private array $options = [
        'TWD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD',
        'BND', 'BOB', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CUP', 'CVE', 'CZK',
        'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'FOK', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF',
        'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY',
        'KES', 'KGS', 'KHR', 'KID', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA',
        'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR',
        'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD',
        'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'SSP', 'STN', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TVD', 'TZS',
        'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XDR', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW', 'ZWL',
    ];

    /**
     * @return void
     */
    public function __construct(private OptionServiceRepositoryInterface $optionRepository) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeRate(string $unit = 'TWD'): array
    {
        $unit = strtoupper($unit);

        if (! in_array($unit, $this->options, true)) {
            throw new RuntimeException('unit not found');
        }

        $cacheKey = 'exchangeRate_'.now()->format('Ymd').'_'.$unit;
        $exchangeRate = Cache::remember($cacheKey, now()->addHour(), function () use ($unit): array {
            $url = (string) config('services.exchangeRate.domain', '');
            if ($url === '') {
                return [];
            }

            $data = Http::timeout(15)->get($url.$unit)->json();

            return is_array($data) ? $data : [];
        });

        $rates = (array) data_get($exchangeRate, 'rates', []);

        return [
            'option' => array_keys($rates),
            'rates' => $rates,
            'updated_at' => (string) data_get($exchangeRate, 'date', now()->toDateString()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->optionRepository->listCategories();
    }
}
