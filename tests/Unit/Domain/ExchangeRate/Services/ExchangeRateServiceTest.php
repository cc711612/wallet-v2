<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ExchangeRate\Services;

use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;
use App\Domain\ExchangeRate\Services\ExchangeRateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    public function test_it_keeps_saving_other_currencies_when_one_rate_fails(): void
    {
        config(['services.exchangeRate.domain' => 'https://rates.test/']);
        Http::fake([
            'rates.test/*' => Http::response([
                'date' => '2026-10-13',
                'rates' => ['EUR' => 0.9, 'IRR' => 1653006.5, 'TWD' => 32.1],
            ]),
        ]);
        $repository = new FailingExchangeRateRepository(['IRR']);

        (new ExchangeRateService($repository))->setExchangeRate();

        $this->assertSame([
            'TWD:EUR', 'TWD:TWD',
            'USD:EUR', 'USD:TWD',
        ], $repository->saved);
    }

    public function test_history_update_keeps_saving_other_currencies_when_one_rate_fails(): void
    {
        $repository = new FailingExchangeRateRepository(['IRR']);

        (new ExchangeRateService($repository))->updateHistoryByHistoryResult([
            'result' => 'success',
            'base_code' => 'USD',
            'year' => 2026,
            'month' => 10,
            'day' => 3,
            'conversion_rates' => ['IRR' => 1653006.5, 'JPY' => 149.2, 'TWD' => 32.1],
        ]);

        $this->assertSame(['USD:JPY', 'USD:TWD'], $repository->saved);
        $this->assertSame('2026-10-03', $repository->lastDate);
    }

    public function test_it_throws_when_every_rate_fails(): void
    {
        $repository = new FailingExchangeRateRepository(['IRR', 'JPY']);

        $this->expectException(QueryException::class);

        (new ExchangeRateService($repository))->updateHistoryByHistoryResult([
            'result' => 'success',
            'base_code' => 'USD',
            'year' => 2026,
            'month' => 10,
            'day' => 3,
            'conversion_rates' => ['IRR' => 1653006.5, 'JPY' => 149.2],
        ]);
    }
}

class FailingExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    /** @var array<int, string> */
    public array $saved = [];

    public ?string $lastDate = null;

    /**
     * @param  array<int, string>  $failingCurrencies
     */
    public function __construct(private array $failingCurrencies) {}

    public function upsertByDateAndCurrency(array $keys, array $values): void
    {
        if (in_array($keys['to_currency'], $this->failingCurrencies, true)) {
            throw new QueryException('mysql', 'insert into `exchange_rates`', [], new RuntimeException('Out of range value for column \'rate\''));
        }

        $this->saved[] = $keys['from_currency'].':'.$keys['to_currency'];
        $this->lastDate = $keys['date'];
    }

    public function existsByCurrencyAndDate(string $fromCurrency, string $date): bool
    {
        return false;
    }
}
