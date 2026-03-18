<?php

declare(strict_types=1);

namespace App\Domain\Log\Services;

use Illuminate\Support\Facades\Log;

class FrontLogService
{
    /**
     * 記錄一般等級前端訊息。
     */
    public function normal(string $message): void
    {
        Log::info('front.normal', ['message' => $message]);
    }

    /**
     * 記錄嚴重等級前端訊息。
     */
    public function serious(string $message): void
    {
        Log::critical('front.serious', ['message' => $message]);
    }
}
