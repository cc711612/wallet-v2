<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Entities\WalletUserEntity;
use App\Jobs\NotificationFCM;

class WalletDetailObserver
{
    public function created(WalletDetailEntity $walletDetailEntity): void
    {
        if ((int) $walletDetailEntity->is_personal === 1) {
            return;
        }

        $wallet = $walletDetailEntity->wallet()->first(['id', 'title']);
        if ($wallet === null) {
            return;
        }

        $walletUsers = WalletUserEntity::query()
            ->where('wallet_id', $walletDetailEntity->wallet_id)
            ->where('notify_enable', 1)
            ->get(['id']);

        foreach ($walletUsers as $walletUser) {
            $message = implode("\r\n", [
                '有一筆新的記帳資料',
                '帳本名稱：'.$wallet->title,
                '記帳日期：'.$walletDetailEntity->date,
                '記帳名稱：'.$walletDetailEntity->title,
                '記帳金額：'.number_format((float) $walletDetailEntity->value),
            ]);

            NotificationFCM::dispatch((int) $walletDetailEntity->id, (int) $walletUser->id, $message);
        }
    }

    public function updated(WalletDetailEntity $walletDetailEntity): void
    {
        // handled by WalletDetailEntity::$touches
    }
}
