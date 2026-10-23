<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\Exceptions\NotifyV2RetryableException;
use App\Domain\Notification\Services\NotificationJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationFCM implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [5, 30];

    /**
     * notify v2 用。月結推播在 dispatch 時帶入並隨 job 序列化；明細推播不帶（null），
     * 由 NotificationJobService 在 handle 時依 walletDetailId + walletUserId 推導。兩種方式重試時 key 都不變。
     * 不用 constructor promotion：部署前已排入 queue 的舊 job 反序列化時沒有這個欄位，需有預設值 null。
     */
    private ?string $idempotencyKey = null;

    public function __construct(
        private int $walletDetailId,
        private int $walletUserId,
        private string $message,
        ?string $idempotencyKey = null,
    ) {
        $this->idempotencyKey = $idempotencyKey;
        $this->onQueue('send_message');
    }

    public function handle(NotificationJobService $notificationJobService): void
    {
        try {
            $notificationJobService->sendFcm($this->walletDetailId, $this->walletUserId, $this->message, $this->idempotencyKey);
        } catch (NotifyV2RetryableException $exception) {
            if ($exception->retryAfterSeconds === null) {
                // 沒有指定延後秒數：丟出去，沿用 $backoff 重試
                throw $exception;
            }

            // 429 Retry-After／409 idempotency_in_progress：依指定秒數延後重試（同一個 key）
            $this->release($exception->retryAfterSeconds);
        }
    }

    /**
     * 重試用盡仍失敗（例如 notify v2 持續 503）。
     */
    public function failed(Throwable $exception): void
    {
        Log::error('NotificationFCM failed after retries', [
            'wallet_detail_id' => $this->walletDetailId,
            'wallet_user_id' => $this->walletUserId,
            'idempotency_key' => $this->idempotencyKey,
            'error' => substr($exception->getMessage(), 0, 300),
        ]);
    }
}
