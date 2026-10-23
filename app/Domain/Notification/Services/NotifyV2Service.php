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
 * - 409 idempotency_conflict / idempotency_unrecorded：同一個 key 只會送一次，不重試，記 warning log。
 * - 409 idempotency_in_progress：同 key 仍在處理中，延後 IN_PROGRESS_RETRY_SECONDS 秒重試（同一個 key）。
 * - 429：依 Retry-After 延後重試（上限 MAX_RETRY_AFTER_SECONDS 秒）；沒有 Retry-After 時沿用 job backoff。
 * - 502、503、其他 5xx、連線錯誤／逾時：丟 NotifyV2RetryableException 交給 queue job 重試（同一個 key）。
 * - 其他 4xx（400/401/403/404/413/415/422）：設定或資料錯誤，不重試，記 error log。
 */
class NotifyV2Service
{
    /** notify 每次請求最多 500 個 token */
    private const MAX_TOKENS_PER_REQUEST = 500;

    /** notify 的 token 格式限制（不符會讓整個請求 400） */
    private const TOKEN_PATTERN = '/^[A-Za-z0-9_:\-.]{32,4096}$/';

    /** notify 的 notification.title 上限（bytes） */
    private const MAX_TITLE_BYTES = 1024;

    /** notify 的 notification.body 上限（bytes） */
    private const MAX_BODY_BYTES = 4000;

    /** notify 的 URL 長度上限（bytes） */
    private const MAX_URL_BYTES = 2048;

    private const ELLIPSIS = '…';

    /** 429 Retry-After 的延後上限（秒） */
    public const MAX_RETRY_AFTER_SECONDS = 300;

    /** 409 idempotency_in_progress 的延後秒數 */
    public const IN_PROGRESS_RETRY_SECONDS = 15;

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
        if (trim((string) config('services.notify_v2.url', '')) === '') {
            Log::error('NotifyV2Service url not configured', ['idempotency_key' => $idempotencyKey]);

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

        $title = $this->truncateUtf8($title, self::MAX_TITLE_BYTES);
        $body = $this->truncateUtf8($body, self::MAX_BODY_BYTES);
        $webpush = $this->buildWebpush($idempotencyKey, $data);

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
            if ($webpush !== []) {
                $payload['webpush'] = $webpush;
            }

            $this->handleResponse($key, $this->notifyV2Repository->sendMessage($payload));
        }
    }

    /**
     * 超過 notify 上限時截成合法 UTF-8 並加上「…」（總長不超過上限）。
     */
    private function truncateUtf8(string $text, int $maxBytes): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        // 保留 10 bytes 給「…」（3 bytes）與餘裕；mb_strcut 不會切在多位元組字元中間
        return mb_strcut($text, 0, $maxBytes - 10, 'UTF-8').self::ELLIPSIS;
    }

    /**
     * webpush.link / icon 必須是完整 https 網址，否則 notify 會整包 400；不合格的欄位不送並記 warning。
     *
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function buildWebpush(string $idempotencyKey, array $data): array
    {
        $webpush = [];
        foreach (['link', 'icon'] as $field) {
            $value = $data[$field] ?? null;
            if ($value === null) {
                continue;
            }
            if ($this->isAbsoluteHttpsUrl($value)) {
                $webpush[$field] = $value;

                continue;
            }

            Log::warning('NotifyV2Service dropped invalid webpush url', [
                'idempotency_key' => $idempotencyKey,
                'field' => 'webpush.'.$field,
                'value' => substr($value, 0, 200),
            ]);
        }

        return $webpush;
    }

    private function isAbsoluteHttpsUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_URL_BYTES) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && (string) ($parts['host'] ?? '') !== '';
    }

    /**
     * @param  array{status: int, json: array<string, mixed>|null, error: string, retry_after?: int|null}  $result
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

        if ($status === 409 && $errorCode === 'idempotency_in_progress') {
            // 同 key 的上一次請求仍在處理中：延後再用同一個 key 查結果（完成後會回 replayed）
            Log::warning('NotifyV2Service retryable failure', $context);

            throw $this->retryable($status, $errorCode, $idempotencyKey, self::IN_PROGRESS_RETRY_SECONDS);
        }

        if ($status === 409) {
            // idempotency_conflict / idempotency_unrecorded：這個 key 已用過，不再送
            Log::warning('NotifyV2Service idempotency conflict, not retrying', $context);

            return;
        }

        if ($status === 0 || $status === 429 || $status >= 500) {
            $retryAfter = null;
            if ($status === 429 && ($result['retry_after'] ?? null) !== null) {
                $retryAfter = min(max(1, (int) $result['retry_after']), self::MAX_RETRY_AFTER_SECONDS);
            }
            $context['retry_after'] = $retryAfter;
            Log::warning('NotifyV2Service retryable failure', $context);

            throw $this->retryable($status, $errorCode, $idempotencyKey, $retryAfter);
        }

        Log::error('NotifyV2Service request rejected', $context);
    }

    private function retryable(int $status, string $errorCode, string $idempotencyKey, ?int $retryAfter): NotifyV2RetryableException
    {
        return new NotifyV2RetryableException(sprintf(
            'notify v2 retryable failure: status=%d code=%s key=%s',
            $status,
            $errorCode !== '' ? $errorCode : '-',
            $idempotencyKey,
        ), $retryAfter);
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
