<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
use Illuminate\Support\Facades\Log;

class NotificationJobService
{
    /**
     * Firebase 金鑰內容快取（同一個 process 內檔案內容不變動，避免每次都重讀）。
     *
     * @var array<string, mixed>|null
     */
    private static ?array $firebaseParamsCache = null;

    private static ?int $firebaseParamsCacheMtime = null;

    /**
     * 建立通知工作服務。
     *
     * @return void
     */
    public function __construct(private NotificationJobRepositoryInterface $notificationJobRepository) {}

    /**
     * 送出 FCM 批次通知。
     */
    public function sendFcm(int $walletDetailId, int $walletUserId, string $message): void
    {
        $walletUser = $this->notificationJobRepository->findWalletUserWithWallet($walletUserId);
        if ($walletUser === null) {
            return;
        }

        $devices = $this->notificationJobRepository->listActiveDevicesByOwner(
            isset($walletUser['user_id']) ? (int) $walletUser['user_id'] : null,
            $walletUserId,
        );
        if ($devices === []) {
            return;
        }

        $walletId = (string) ($walletUser['wallet_id'] ?? '');
        $ledgerCode = (string) ($walletUser['wallet']['code'] ?? '');

        $appUrl = rtrim((string) config('services.easysplit.app_url', ''), '/');
        $logoPath = (string) config('services.easysplit.logo_path', '');
        $frameIconPath = (string) config('services.easysplit.frame_icon_path', '');
        $botId = (string) config('services.easysplit.bot_id', '');

        $firebaseParams = $this->loadFirebaseParams();

        $requestBody = [
            'platform' => 'FCM',
            'targetId' => (string) $walletDetailId,
            'platformBotId' => $botId,
            'platformParameters' => $firebaseParams,
            'webhookUrl' => url('/api/auth/cache'),
            'users' => array_map(static function (array $device) use ($walletUser, $message, $walletId, $ledgerCode, $appUrl, $logoPath, $frameIconPath): array {
                $targetId = (int) ($device['wallet_user_id'] ?? 0) ?: (int) ($device['user_id'] ?? 0);

                return [
                    'userId' => $targetId,
                    'userName' => (string) ($walletUser['name'] ?? ''),
                    'notificationId' => (string) ($device['fcm_token'] ?? ''),
                    'messages' => [
                        'title' => 'Easysplit',
                        'content' => $message,
                        'icon' => $appUrl.$logoPath,
                        'click_action' => $appUrl.'/',
                        'data' => [
                            "wallet_id" => $walletId,
                            "ledger_code" => $ledgerCode,
                            "type" => "wallet_record_created",
                            "link" => "{$appUrl}/ledger/{$ledgerCode}/detail",
                            "icon" => $appUrl.$frameIconPath,
                        ],
                    ],
                ];
            }, $devices),
        ];

        $this->sendFcmBatchWithRetry($requestBody);
    }

    /**
     * 帶重試機制送出 FCM 批次通知，失敗時記錄 HTTP status 與 response body 摘要。
     *
     * @param  array<string, mixed>  $requestBody
     */
    private function sendFcmBatchWithRetry(array $requestBody): void
    {
        $retryTimes = max(1, (int) config('services.notification.retry_times', 3));
        $retryDelayMs = (int) config('services.notification.retry_delay_ms', 500);

        $result = ['success' => false, 'status' => 0, 'body' => ''];

        for ($attempt = 1; $attempt <= $retryTimes; $attempt++) {
            $result = $this->notificationJobRepository->sendFcmBatch($requestBody);

            if ($result['success']) {
                return;
            }

            Log::warning('NotificationJobService sendFcm attempt failed', [
                'attempt' => $attempt,
                'max_attempts' => $retryTimes,
                'status' => $result['status'],
                'body' => substr((string) $result['body'], 0, 500),
            ]);

            if ($attempt < $retryTimes && $retryDelayMs > 0) {
                usleep($retryDelayMs * 1000);
            }
        }

        Log::error('NotificationJobService sendFcm failed', [
            'status' => $result['status'],
            'body' => substr((string) $result['body'], 0, 500),
            'attempts' => $retryTimes,
        ]);
    }

    /**
     * 讀取（並快取）Firebase 金鑰內容。
     *
     * @return array<string, mixed>
     */
    private function loadFirebaseParams(): array
    {
        $firebaseKeyPath = storage_path('easysplit-firebase-key.json');
        // 以 mtime 當快取失效條件：長駐 worker（Octane/queue）下 key rotation 換檔後
        // 不需重啟 process 即可讀到新 key；stat 開銷遠小於每次重讀+decode
        $mtime = is_file($firebaseKeyPath) ? (filemtime($firebaseKeyPath) ?: null) : null;

        if (self::$firebaseParamsCache !== null && self::$firebaseParamsCacheMtime === $mtime) {
            return self::$firebaseParamsCache;
        }

        $firebaseParams = [];
        if ($mtime !== null) {
            $decoded = json_decode((string) file_get_contents($firebaseKeyPath), true);
            $firebaseParams = is_array($decoded) ? $decoded : [];
        }

        self::$firebaseParamsCache = $firebaseParams;
        self::$firebaseParamsCacheMtime = $mtime;

        return $firebaseParams;
    }
}
