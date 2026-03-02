<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Socials;

use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Socials\SocialBindRequest;
use App\Http\Requests\Apis\Socials\SocialCheckBindRequest;
use App\Http\Requests\Apis\Socials\SocialUnBindRequest;
use App\Http\Resources\Socials\SocialCheckBindResource;
use Illuminate\Http\JsonResponse;

class SocialController extends ApiController
{
    public function checkBind(SocialCheckBindRequest $request, SocialService $socialService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new SocialCheckBindResource($socialService->checkBind($validated)));
    }

    public function bind(SocialBindRequest $request, SocialService $socialService): JsonResponse
    {
        $validated = $request->validated();

        $socialService->bind($validated);

        return $this->response()->success();
    }

    public function unBind(SocialUnBindRequest $request, SocialService $socialService): JsonResponse
    {
        $validated = $request->validated();

        $socialService->unBind($validated);

        return $this->response()->success();
    }
}
