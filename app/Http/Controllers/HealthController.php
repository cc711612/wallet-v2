<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends ApiController
{
    /**
     * 執行系統健康檢查。
     */
    #[Response(200, '健康檢查成功', type: 'array{status: bool, code: int, message: string, data: array{db: array{ok: bool, message: string}, cache: array{ok: bool, message: string}, runtime: array{sapi: string, frankenphp: bool}}}')]
    #[Response(503, '健康檢查異常', type: 'array{status: false, code: 503, message: string, data: array{db: array{ok: bool, message: string}, cache: array{ok: bool, message: string}, runtime: array{sapi: string, frankenphp: bool}}}')]
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
