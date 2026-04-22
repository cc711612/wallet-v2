<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Jobs\LineWebhookJob;
use App\Jobs\SendLineMessage;

class LineWebhookService
{
    public function __construct(
        private LineWebhookJobRepositoryInterface $lineWebhookJobRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        $events = (array) ($payload['events'] ?? []);

        foreach ($events as $event) {
            $lineUserId = (string) data_get($event, 'source.userId', '');
            $replyToken = (string) data_get($event, 'replyToken', '');
            $messageType = (string) data_get($event, 'message.type', '');

            if ($lineUserId === '' || $replyToken === '' || $messageType !== 'text') {
                continue;
            }

            $this->lineWebhookJobRepository->startLoading($lineUserId);
        }

        LineWebhookJob::dispatch($payload);

        return ['received' => true, 'event_count' => count($events)];
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
