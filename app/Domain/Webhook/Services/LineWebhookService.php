<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Jobs\LineWebhookJob;

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
}
