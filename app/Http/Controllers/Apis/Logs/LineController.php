<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Logs;

use App\Domain\Webhook\Services\LineWebhookService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineController extends ApiController
{
    /**
     * 接收並儲存 LINE webhook 事件。
     *
     * @param  Request  $request
     * @param  LineWebhookService  $lineWebhookService
     * @return JsonResponse
     */
    public function store(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->store($request->all());

        return $this->response()->success();
    }

    /**
     * 處理 LINE 通知 webhook。
     *
     * @param  Request  $request
     * @param  LineWebhookService  $lineWebhookService
     * @return JsonResponse
     */
    public function notify(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    /**
     * 取得 LINE 綁定通知網址。
     *
     * @param  LineWebhookService  $lineWebhookService
     * @return JsonResponse
     */
    public function notifyBind(LineWebhookService $lineWebhookService): JsonResponse
    {
        return $this->response()->success($lineWebhookService->notifyBindUrl());
    }

    /**
     * 產生 LINE 通知 token。
     *
     * @param  Request  $request
     * @param  LineWebhookService  $lineWebhookService
     * @return JsonResponse
     */
    public function notifyToken(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    /**
     * 測試發送 LINE 訊息。
     *
     * @param  Request  $request
     * @param  LineWebhookService  $lineWebhookService
     * @return JsonResponse
     */
    public function notifySendMessage(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }
}
