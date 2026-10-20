<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * P0-14：redis retry_after 必須大於 supervisor worker 的 --timeout，否則長 job 會被重複執行。
 */
class QueueConfigTest extends TestCase
{
    public function test_redis_retry_after_is_greater_than_every_worker_timeout(): void
    {
        $ini = (string) file_get_contents(base_path('deployment/production/supervisor/supervisor.d/laravel-worker.ini'));
        preg_match_all('/--timeout=(\d+)/', $ini, $matches);

        $this->assertNotEmpty($matches[1], 'laravel-worker.ini 應設定 --timeout');

        $retryAfter = (int) config('queue.connections.redis.retry_after');
        foreach ($matches[1] as $timeout) {
            $this->assertGreaterThan((int) $timeout, $retryAfter);
        }
    }
}
