<?php

declare(strict_types=1);

namespace App\Domain\Gemini\Exceptions;

use RuntimeException;

/**
 * Gemini 暫時性失敗（流量限制、服務過載等），稍後重試通常會成功。
 */
class GeminiTransientException extends RuntimeException {}
