<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Jobs\NotificationFCM;
use Illuminate\Console\Command;

class AutoCalculateWalletCommand extends Command
{
    protected $signature = 'command:auto_calculate_wallet';

    protected $description = 'Auto calculate monthly wallet settlement';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = (int) env('AUTO_WALLET_USER_ID', 58);
        $title = now()->subMonth()->format('Y.m');

        $wallet = WalletEntity::query()->where('user_id', $userId)->where('title', $title)->first(['id', 'title']);
        if ($wallet === null) {
            $this->info("No wallet found for {$title}, skip.");

            return self::SUCCESS;
        }

        $walletDetails = WalletDetailEntity::query()
            ->where('wallet_id', (int) $wallet->id)
            ->where('is_personal', 0)
            ->get(['type', 'payment_wallet_user_id', 'symbol_operation_type_id', 'value']);

        $messages = ['帳本名稱: '.(string) $wallet->title];
        $total = 0.0;

        $publicTotal = (float) $walletDetails
            ->where('type', WalletDetailType::PUBLIC_EXPENSE->value)
            ->where('symbol_operation_type_id', SymbolOperationType::DECREMENT->value)
            ->sum('value');
        $total += $publicTotal;
        $messages[] = '公費總支出金額: '.$publicTotal;

        $walletUsers = WalletUserEntity::query()
            ->where('wallet_id', (int) $wallet->id)
            ->get(['id', 'notify_enable', 'name']);

        foreach ($walletUsers as $walletUser) {
            $messages[] = '帳本成員: '.(string) $walletUser->name;
            $userPaymentTotal = (float) $walletDetails
                ->where('type', WalletDetailType::GENERAL_EXPENSE->value)
                ->where('payment_wallet_user_id', (int) $walletUser->id)
                ->where('symbol_operation_type_id', SymbolOperationType::DECREMENT->value)
                ->sum('value');
            $total += $userPaymentTotal;
            $messages[] = '帳本成員總代墊金額: '.$userPaymentTotal;
        }

        $messages[] = '總支出金額: '.$total;
        $messages[] = '結算時間: '.now()->format('Y-m-d H:i:s');

        $message = implode("\r\n", $messages);

        $walletUsers = $walletUsers->where('notify_enable', 1)->values();

        foreach ($walletUsers as $walletUser) {
            NotificationFCM::dispatch((int) $wallet->id, (int) $walletUser->id, $message);
        }

        $this->info('Auto calculate wallet executed.');

        return self::SUCCESS;
    }
}
