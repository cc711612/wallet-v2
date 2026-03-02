<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Logs;

use App\Domain\Log\Services\FrontLogService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontLogController extends ApiController
{
    public function normal(Request $request, FrontLogService $frontLogService): JsonResponse
    {
        $frontLogService->normal((string) $request->input('message', ''));

        return $this->response()->success();
    }

    public function serious(Request $request, FrontLogService $frontLogService): JsonResponse
    {
        $frontLogService->serious((string) $request->input('message', ''));

        return $this->response()->success();
    }
}
