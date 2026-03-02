<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Webhook\Services\LineWebhookJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLineMessage implements ShouldQueue
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
        $this->onQueue('send_message');
    }

    public function handle(LineWebhookJobService $lineWebhookJobService): void
    {
        $lineWebhookJobService->relayNotifySendMessage($this->params);

        Log::info('SendLineMessage handled', ['params' => $this->params]);
    }
}
