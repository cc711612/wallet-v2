<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Logs;

use App\Domain\Log\Services\FrontLogService;
use App\Http\Controllers\ApiController;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontLogController extends ApiController
{
    /**
     * 記錄前端一般等級日誌。
     */
    #[Response(200, '寫入一般日誌成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function normal(Request $request, FrontLogService $frontLogService): JsonResponse
    {
        $frontLogService->normal((string) $request->input('message', ''));

        return $this->response()->success();
    }

    /**
     * 記錄前端嚴重等級日誌。
     */
    #[Response(200, '寫入嚴重日誌成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function serious(Request $request, FrontLogService $frontLogService): JsonResponse
    {
        $frontLogService->serious((string) $request->input('message', ''));

        return $this->response()->success();
    }
}
