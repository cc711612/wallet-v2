<?php

declare(strict_types=1);

namespace App\Models\Logging\Services;

use Illuminate\Support\Facades\Config;

class LoggingService
{
    /**
     * 設定 log 擴充欄位。
     *
     * @param  string|null  $prefix
     * @param  string|null  $deviceUuid
     * @param  string|null  $requestUuid
     * @return void
     */
    public static function setLoggingConfig(?string $prefix = null, ?string $deviceUuid = null, ?string $requestUuid = null): void
    {
        self::setPrefix($prefix);
        self::setDeviceUuid($deviceUuid);
        self::setRequestUuid($requestUuid);
    }

    /**
     * @param  string|null  $prefix
     * @return void
     */
    public static function setPrefix(?string $prefix = null): void
    {
        Config::set('logging.extend.prefix', $prefix);
    }

    /**
     * @param  string|null  $uuid
     * @return void
     */
    public static function setDeviceUuid(?string $uuid = null): void
    {
        Config::set('logging.extend.device_uuid', $uuid);
    }

    /**
     * @param  string|null  $uuid
     * @return void
     */
    public static function setRequestUuid(?string $uuid = null): void
    {
        Config::set('logging.extend.request_uuid', $uuid);
    }

    /**
     * @return array<string, string|null>
     */
    public static function getLoggerExtend(): array
    {
        return [
            'prefix' => (string) (config('logging.extend.prefix') ?: 'UNKNOWN'),
            'deviceUuid' => config('logging.extend.device_uuid'),
            'requestUuid' => config('logging.extend.request_uuid'),
        ];
    }
}
