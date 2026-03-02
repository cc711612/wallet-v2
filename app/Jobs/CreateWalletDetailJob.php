<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Wallet\Services\CreateWalletDetailJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateWalletDetailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        private int $userId,
        private int $walletId,
        private array $params,
    ) {
        $this->onQueue('handle_register');
    }

    public function handle(CreateWalletDetailJobService $createWalletDetailJobService): void
    {
        $createWalletDetailJobService->createGeneralExpenseDetail($this->userId, $this->walletId, $this->params);
    }
}
