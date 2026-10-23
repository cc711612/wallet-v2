<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Device\Entities\DeviceEntity;
use App\Domain\Notification\Repositories\NotifyV2RepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class NotifyV2Repository implements NotifyV2RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, json: array<string, mixed>|null, error: string}
     */
    public function sendMessage(array $payload): array
    {
        $url = rtrim((string) config('services.notify_v2.url', ''), '/');
        $token = (string) config('services.notify_v2.token', '');
        // notify 的請求總期限是 35s，逾時要比它長才拿得到 deadline_exceeded 等明確回應
        $timeout = max(1, (int) config('services.notify_v2.timeout', 40));

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($url.'/api/v2/messages', $payload);
        } catch (ConnectionException $exception) {
            return ['status' => 0, 'json' => null, 'error' => $exception->getMessage()];
        }

        $json = $response->json();

        return [
            'status' => $response->status(),
            'json' => is_array($json) ? $json : null,
            'error' => '',
        ];
    }

    /**
     * @param  array<int, string>  $fcmTokens
     */
    public function expireDevicesByFcmTokens(array $fcmTokens): int
    {
        $fcmTokens = array_values(array_unique(array_filter($fcmTokens, static fn ($token): bool => is_string($token) && $token !== '')));
        if ($fcmTokens === []) {
            return 0;
        }

        $now = now();

        return DeviceEntity::query()
            ->whereIn('fcm_token', $fcmTokens)
            ->where('expired_at', '>', $now)
            ->update(['expired_at' => $now]);
    }
}
