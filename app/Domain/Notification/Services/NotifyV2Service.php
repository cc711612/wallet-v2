<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Exceptions\NotifyV2RetryableException;
use App\Domain\Notification\Repositories\NotifyV2RepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * 透過 notify v2（POST /api/v2/messages）送 FCM 推播。
 *
 * 回應處理：
 * - 200：invalid_tokens 對應的裝置設為過期；有 warning 記 warning log。
 * - 409（idempotency_*）：同一個 key 只會送一次，不重試，記 warning log。
 * - 429、502、503、其他 5xx、連線錯誤／逾時：丟 NotifyV2RetryableException 交給 queue job 重試（同一個 key）。
 * - 其他 4xx（400/401/403/404/413/415/422）：設定或資料錯誤，不重試，記 error log。
 */
class NotifyV2Service
{
    /** notify 每次請求最多 500 個 token */
    private const MAX_TOKENS_PER_REQUEST = 500;

    /** notify 的 token 格式限制（不符會讓整個請求 400） */
    private const TOKEN_PATTERN = '/^[A-Za-z0-9_:\-.]{32,4096}$/';

    public function __construct(private NotifyV2RepositoryInterface $notifyV2Repository) {}

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, mixed>  $data
     *
     * @throws NotifyV2RetryableException
     */
    public function send(string $idempotencyKey, array $tokens, string $title, string $body, array $data): void
    {
        if ((string) config('services.notify_v2.token', '') === '') {
            Log::error('NotifyV2Service token not configured', ['idempotency_key' => $idempotencyKey]);

            return;
        }

        $validTokens = [];
        $skipped = 0;
        foreach ($tokens as $token) {
            if (is_string($token) && preg_match(self::TOKEN_PATTERN, $token) === 1) {
                $validTokens[$token] = true;
            } else {
                $skipped++;
            }
        }
        $validTokens = array_keys($validTokens);

        if ($skipped > 0) {
            Log::warning('NotifyV2Service skipped malformed fcm tokens', [
                'idempotency_key' => $idempotencyKey,
                'skipped' => $skipped,
            ]);
        }
        if ($validTokens === []) {
            return;
        }

        $data = array_map(static fn ($value): string => match (true) {
            $value === null => '',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        }, $data);

        $chunks = array_chunk($validTokens, self::MAX_TOKENS_PER_REQUEST);
        foreach ($chunks as $index => $chunk) {
            // 超過 500 個 token 才分批；分批的 key 加上批次序號，確保每批各自冪等
            $key = count($chunks) === 1 ? $idempotencyKey : $idempotencyKey.'-c'.$index;

            $payload = [
                'idempotency_key' => $key,
                'tokens' => $chunk,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
            ];
            $webpush = array_filter([
                'link' => $data['link'] ?? null,
                'icon' => $data['icon'] ?? null,
            ], static fn (?string $value): bool => $value !== null && $value !== '');
            if ($webpush !== []) {
                $payload['webpush'] = $webpush;
            }

            $this->handleResponse($key, $this->notifyV2Repository->sendMessage($payload));
        }
    }

    /**
     * @param  array{status: int, json: array<string, mixed>|null, error: string}  $result
     *
     * @throws NotifyV2RetryableException
     */
    private function handleResponse(string $idempotencyKey, array $result): void
    {
        $status = $result['status'];
        $json = $result['json'] ?? [];
        $errorCode = is_array($json['error'] ?? null) ? (string) ($json['error']['code'] ?? '') : '';

        if ($status === 200) {
            $this->handleSuccess($idempotencyKey, $json);

            return;
        }

        $context = [
            'idempotency_key' => $idempotencyKey,
            'status' => $status,
            'code' => $errorCode,
            'error' => $status === 0 ? substr($result['error'], 0, 300) : '',
        ];

        if ($status === 409) {
            // idempotency_conflict / in_progress / unrecorded：這個 key 已處理過或處理中，不再送
            Log::warning('NotifyV2Service idempotency conflict, not retrying', $context);

            return;
        }

        if ($status === 0 || $status === 429 || $status >= 500) {
            Log::warning('NotifyV2Service retryable failure', $context);

            throw new NotifyV2RetryableException(sprintf(
                'notify v2 retryable failure: status=%d code=%s key=%s',
                $status,
                $errorCode !== '' ? $errorCode : '-',
                $idempotencyKey,
            ));
        }

        Log::error('NotifyV2Service request rejected', $context);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function handleSuccess(string $idempotencyKey, array $json): void
    {
        $invalidTokens = array_values(array_filter(
            (array) ($json['invalid_tokens'] ?? []),
            static fn ($token): bool => is_string($token) && $token !== '',
        ));

        $expired = $invalidTokens === [] ? 0 : $this->notifyV2Repository->expireDevicesByFcmTokens($invalidTokens);

        Log::info('NotifyV2Service sent', [
            'idempotency_key' => $idempotencyKey,
            'message_id' => $json['message_id'] ?? null,
            'status' => $json['status'] ?? null,
            'replayed' => $json['replayed'] ?? null,
            'sent' => $json['sent'] ?? null,
            'failed' => $json['failed'] ?? null,
            'invalid_tokens' => count($invalidTokens),
            'expired_devices' => $expired,
        ]);

        $warning = $json['warning'] ?? null;
        if (is_array($warning)) {
            Log::warning('NotifyV2Service response warning', [
                'idempotency_key' => $idempotencyKey,
                'message_id' => $json['message_id'] ?? null,
                'code' => (string) ($warning['code'] ?? ''),
                'message' => (string) ($warning['message'] ?? ''),
            ]);
        }
    }
}
