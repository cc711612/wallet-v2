<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exceptions;

use RuntimeException;

/**
 * notify v2 回應可重試的失敗（503、429、502、其他 5xx、連線錯誤／逾時）。
 * 由 queue job 依 $tries / $backoff 重試；job 參數不變，重試時 idempotency_key 相同，notify 端不會重複推播。
 */
class NotifyV2RetryableException extends RuntimeException {}
