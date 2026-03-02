<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Logs;

use App\Domain\Webhook\Services\LineWebhookService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineController extends ApiController
{
    public function store(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->store($request->all());

        return $this->response()->success();
    }

    public function notify(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    public function notifyBind(LineWebhookService $lineWebhookService): JsonResponse
    {
        return $this->response()->success($lineWebhookService->notifyBindUrl());
    }

    public function notifyToken(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }

    public function notifySendMessage(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $lineWebhookService->notify($request->all());

        return $this->response()->success();
    }
}
