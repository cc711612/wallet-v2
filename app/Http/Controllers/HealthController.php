<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends ApiController
{
    public function check(): \Illuminate\Http\JsonResponse
    {
        $db = $this->checkDb();
        $cache = $this->checkCache();
        $runtime = $this->checkRuntime();

        $allHealthy = $db['ok'] && $cache['ok'];

        return $this->response()->success(
            data: [
                'db' => $db,
                'cache' => $cache,
                'runtime' => $runtime,
            ],
            code: $allHealthy ? 200 : 503,
        );
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDb(): array
    {
        try {
            DB::select('SELECT 1');

            return ['ok' => true, 'message' => 'ok'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{sapi: string, frankenphp: bool}
     */
    private function checkRuntime(): array
    {
        $sapi = php_sapi_name();

        return [
            'sapi' => $sapi,
            'frankenphp' => $sapi === 'frankenphp',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkCache(): array
    {
        try {
            $key = '_health_check';
            Cache::put($key, 1, 5);
            $hit = Cache::get($key) == 1;
            Cache::forget($key);

            return $hit
                ? ['ok' => true, 'message' => 'ok']
                : ['ok' => false, 'message' => 'read back failed'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
