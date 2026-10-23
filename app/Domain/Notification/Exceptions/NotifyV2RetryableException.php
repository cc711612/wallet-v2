<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exceptions;

use RuntimeException;

/**
 * notify v2 回應可重試的失敗（503、429、502、其他 5xx、409 idempotency_in_progress、連線錯誤／逾時）。
 * 由 queue job 依 $tries / $backoff 重試；job 參數不變，重試時 idempotency_key 相同，notify 端不會重複推播。
 */
class NotifyV2RetryableException extends RuntimeException
{
    /**
     * @param  int|null  $retryAfterSeconds  指定延後秒數（429 Retry-After、409 idempotency_in_progress）；null 表示沿用 job 的 backoff
     */
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
