<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Jobs\LineWebhookJob;
use App\Jobs\SendLineMessage;

class LineWebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        LineWebhookJob::dispatch($payload);

        return ['received' => true, 'event_count' => count((array) ($payload['events'] ?? []))];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function notify(array $payload): array
    {
        SendLineMessage::dispatch($payload);

        return ['queued' => true];
    }

    /**
     * @return array<string, string>
     */
    public function notifyBindUrl(): array
    {
        return [
            'url' => '',
            'deprecated' => true,
            'message' => 'LINE Notify 已停止服務，請改用 LINE Bot 綁定。',
        ];
    }
}
