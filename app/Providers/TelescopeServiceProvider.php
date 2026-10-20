<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            $blockUri = [
                '/api/auth/cache',
                '/up',
            ];
            $host = request()->getHost();

            // 檢查主機是否為 IP 地址
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return false;
            }

            return ! in_array(request()->getRequestUri(), $blockUri)
                && request()->method() != 'OPTIONS';
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'token',
            'member_token',
            'jwt_token',
            'fcm_token',
            'password',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-line-signature',
            'x-goog-api-key',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        // 非 local 環境只允許 TELESCOPE_ALLOWED_IPS 白名單（預設空＝全部拒絕）。
        // 本專案沒有 session 登入，$user 恆為 null，因此以預設值 null 允許 guest 進入判斷。
        Gate::define('viewTelescope', function ($user = null): bool {
            $allowedIps = (array) config('telescope.allowed_ips', []);

            return $allowedIps !== [] && in_array((string) request()->ip(), $allowedIps, true);
        });
    }
}
