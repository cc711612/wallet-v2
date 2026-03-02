<?php

declare(strict_types=1);

namespace App\Domain\Log\Services;

use Illuminate\Support\Facades\Log;

class FrontLogService
{
    public function normal(string $message): void
    {
        Log::info('front.normal', ['message' => $message]);
    }

    public function serious(string $message): void
    {
        Log::critical('front.serious', ['message' => $message]);
    }
}
