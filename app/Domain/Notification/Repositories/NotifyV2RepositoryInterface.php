<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

interface NotifyV2RepositoryInterface
{
    /**
     * 呼叫 notify v2 POST /api/v2/messages。
     * 連線錯誤或逾時時 status 為 0、json 為 null、error 為例外訊息。
     * retry_after 為回應 Retry-After header 的秒數（沒有或無法解析時為 null）。
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, json: array<string, mixed>|null, error: string, retry_after: int|null}
     */
    public function sendMessage(array $payload): array;

    /**
     * 將 fcm_token 在清單內、尚未過期的裝置設為過期。
     *
     * @param  array<int, string>  $fcmTokens
     * @return int 更新筆數
     */
    public function expireDevicesByFcmTokens(array $fcmTokens): int;
}
