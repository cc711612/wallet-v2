<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Wallet\Services\WalletUserRegisterJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class WalletUserRegister implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(private array $params)
    {
        $this->onQueue('handle_register');
    }

    public function handle(WalletUserRegisterJobService $walletUserRegisterJobService): void
    {
        $walletId = (int) Arr::get($this->params, 'wallet.id', 0);
        $walletUserRegisterJobService->syncSelectedDetailsForWalletUsers($walletId);
    }
}
