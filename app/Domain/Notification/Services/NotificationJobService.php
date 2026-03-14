<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
use Illuminate\Support\Facades\Log;

class NotificationJobService
{
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
        $walletUser = $this->notificationJobRepository->findWalletUser($walletUserId);
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

        $firebaseKeyPath = storage_path('easysplit-firebase-key.json');
        $firebaseParams = [];
        if (is_file($firebaseKeyPath)) {
            $decoded = json_decode((string) file_get_contents($firebaseKeyPath), true);
            $firebaseParams = is_array($decoded) ? $decoded : [];
        }

        $requestBody = [
            'platform' => 'FCM',
            'targetId' => (string) $walletDetailId,
            'platformBotId' => 'Easysplit-App',
            'platformParameters' => $firebaseParams,
            'webhookUrl' => url('/api/auth/cache'),
            'users' => array_map(static function (array $device) use ($walletUser, $message): array {
                $targetId = (int) ($device['wallet_user_id'] ?? 0) ?: (int) ($device['user_id'] ?? 0);

                return [
                    'userId' => $targetId,
                    'userName' => (string) ($walletUser['name'] ?? ''),
                    'notificationId' => (string) ($device['fcm_token'] ?? ''),
                    'messages' => [
                        'title' => 'Easysplit',
                        'content' => $message,
                        'icon' => 'https://easysplit.usongrat.tw/images/logo.png',
                        'click_action' => 'https://easysplit.usongrat.tw/',
                    ],
                ];
            }, $devices),
        ];

        if (! $this->notificationJobRepository->sendFcmBatch($requestBody)) {
            Log::error('NotificationJobService sendFcm failed');
        }
    }
}
