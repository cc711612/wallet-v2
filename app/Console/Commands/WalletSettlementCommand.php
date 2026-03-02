<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Console\Command;

class WalletSettlementCommand extends Command
{
    protected $signature = 'wallet:settlement {wallet_id : Wallet ID}';

    protected $description = 'Calculate wallet settlement summary';

    public function __construct(private WalletService $walletService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $walletId = (int) $this->argument('wallet_id');
        $wallet = WalletEntity::query()->find($walletId);
        if ($wallet === null) {
            $this->error("Wallet {$walletId} not found.");

            return self::FAILURE;
        }

        $result = $this->walletService->calculation($walletId);
        $walletData = (array) data_get($result, 'wallet', []);

        $this->info('Wallet settlement summary');
        $this->table(['field', 'value'], [
            ['wallet_id', (string) $walletId],
            ['title', (string) $wallet->title],
            ['income', (string) data_get($walletData, 'total.income', 0)],
            ['expenses', (string) data_get($walletData, 'total.expenses', 0)],
            ['public_income', (string) data_get($walletData, 'total.public.income', 0)],
            ['public_expenses', (string) data_get($walletData, 'total.public.expenses', 0)],
        ]);

        return self::SUCCESS;
    }
}
