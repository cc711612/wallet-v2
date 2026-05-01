<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Services\WalletService;
use App\Jobs\CreateWalletDetailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoCreateWalletCommand extends Command
{
    protected $signature = 'command:auto_create_wallet';

    protected $description = 'Auto create monthly wallet';

    public function __construct(private WalletService $walletService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = (int) env('AUTO_WALLET_USER_ID', 58);
        $user = UserEntity::query()->find($userId, ['id', 'name']);
        if ($user === null) {
            $this->warn("User {$userId} not found, skip auto create wallet.");

            return self::SUCCESS;
        }
        $title = now()->format('Y.m');
        $exists = WalletEntity::query()->where('user_id', $userId)->where('title', $title)->exists();
        if ($exists) {
            $this->info("Wallet {$title} already exists.");

            return self::SUCCESS;
        }

        $result = $this->walletService->store([
            'user' => ['id' => $userId],
            'title' => $title,
            'unit' => 'TWD',
            'mode' => 'multi',
            'owner_name' => (string) $user->name,
        ]);

        $walletId = (int) data_get($result, 'wallet.id', 0);
        if ($walletId <= 0) {
            $this->error('Failed to auto create wallet.');

            return self::FAILURE;
        }

        WalletUserEntity::query()->create([
            'wallet_id' => $walletId,
            'user_id' => 1,
            'name' => 'Roy',
            'token' => Str::random(12),
        ]);

        $seedAmount = (int) env('AUTO_WALLET_SEED_AMOUNT', 943);
        $seedCategoryId = (int) env('AUTO_WALLET_SEED_CATEGORY_ID', 13);
        if ($seedAmount > 0 && $seedCategoryId > 0) {
            CreateWalletDetailJob::dispatch($userId, $walletId, [
                'amount' => $seedAmount,
                'title' => (string) env('AUTO_WALLET_SEED_TITLE', '多多健保'),
                'categoryId' => $seedCategoryId,
                'unit' => 'TWD',
                'date' => now()->format('Y-m-d'),
            ]);
        }

        $this->info("Wallet {$title} created. id={$walletId}");

        return self::SUCCESS;
    }
}
