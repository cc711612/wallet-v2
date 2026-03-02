<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\ExchangeRate\Services\ExchangeRateService;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExchangeRateCommand extends Command
{
    protected $signature = 'command:exchange-rate {--start_date=} {--end_date=} {--currency=}';

    protected $description = 'Backfill exchange rates';

    public function __construct(private ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startDate = $this->option('start_date') ? Carbon::parse((string) $this->option('start_date')) : now();
        $endDate = $this->option('end_date') ? Carbon::parse((string) $this->option('end_date')) : now();
        $currency = strtoupper((string) ($this->option('currency') ?: 'USD'));

        $this->info('開始日期: '.$startDate->toDateString());
        $this->info('結束日期: '.$endDate->toDateString());
        $this->info('幣別: '.$currency);

        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            if ($this->exchangeRateService->isExistExchangeRateByCurrencyAndDate($currency, $dateString)) {
                $this->line('已存在匯率 '.$dateString);

                continue;
            }

            $history = $this->exchangeRateService->getHistoryExchangeRateByCurrencyAndDate($currency, $date->format('Y/m/d'));
            if ($history !== []) {
                $this->exchangeRateService->updateHistoryByHistoryResult($history);
                $this->line('更新匯率 '.$dateString);
            }
        }

        $this->info('done');

        return self::SUCCESS;
    }
}
