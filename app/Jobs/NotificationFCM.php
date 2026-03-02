<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\Services\NotificationJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotificationFCM implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private int $walletDetailId,
        private int $walletUserId,
        private string $message,
    ) {
        $this->onQueue('send_message');
    }

    public function handle(NotificationJobService $notificationJobService): void
    {
        $notificationJobService->sendFcm($this->walletDetailId, $this->walletUserId, $this->message);
    }
}
