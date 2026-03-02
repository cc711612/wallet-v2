<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\ExchangeRate\Services\ExchangeRateService;
use Illuminate\Console\Command;

class DailyUpdateExchangeRate extends Command
{
    protected $signature = 'command:daily_update_exchange_rate';

    protected $description = 'Run hourly exchange rate refresh';

    public function __construct(private ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->exchangeRateService->setExchangeRate();
        $this->info('Exchange rates refreshed.');

        return self::SUCCESS;
    }
}
