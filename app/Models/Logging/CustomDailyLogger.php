<?php

declare(strict_types=1);

namespace App\Models\Logging;

use App\Models\Logging\Formatter\CustomFormatter;
use Illuminate\Log\Logger;

class CustomDailyLogger
{
    /**
     * 套用每日檔案 logger 的自訂格式與檔名策略。
     *
     * @param  Logger  $logger
     * @return void
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            try {
                $currentUserInfo = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                    ? posix_getpwuid(posix_geteuid())
                    : ['uid' => 0, 'name' => get_current_user() ?: 'unknown'];

                if (! is_array($currentUserInfo)) {
                    $currentUserInfo = ['uid' => 0, 'name' => 'unknown'];
                }

                $currentUserId = (int) ($currentUserInfo['uid'] ?? 0);
                $currentUser = (string) ($currentUserInfo['name'] ?? 'unknown');
                $sapi = php_sapi_name();

                if (method_exists($handler, 'setFilenameFormat')) {
                    $handler->setFilenameFormat("{filename}-{$currentUserId}-{$currentUser}-{$sapi}-{date}", 'Y-m-d');
                }

                $handler->setFormatter(new CustomFormatter());
            } catch (\Throwable $exception) {
                error_log('CustomDailyLogger error: '.$exception->getMessage());
            }
        }
    }
}
