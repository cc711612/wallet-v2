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

    /**
     * 處理 LINE 通知 webhook。
     */
    #[Response(200, '處理通知成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function notify(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    /**
     * 取得 LINE 綁定通知網址。
     */
    #[Response(200, '取得通知網址成功', type: 'array{status:true, code:200, message:string, data:array{url:string, deprecated:bool, message:string}}')]
    public function notifyBind(LineWebhookService $lineWebhookService): JsonResponse
    {
        return $this->response()->success($lineWebhookService->notifyBindUrl());
    }

    /**
     * 產生 LINE 通知 token。
     */
    #[Response(200, '產生通知 token 成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function notifyToken(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    /**
     * 測試發送 LINE 訊息。
     */
    #[Response(200, '發送測試訊息成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    public function notifySendMessage(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }
}
