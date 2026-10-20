<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Logs;

use App\Domain\Webhook\Services\LineWebhookService;
use App\Http\Controllers\ApiController;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineController extends ApiController
{
    /**
     * 接收並儲存 LINE webhook 事件。
     */
    #[Response(200, '接收 webhook 成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function store(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->store($request->all());

        return $this->response()->success();
    }
}
