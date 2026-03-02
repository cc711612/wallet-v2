<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Auth\RegisterByTokenRequest;
use App\Http\Requests\Apis\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }

    public function registerByToken(RegisterByTokenRequest $request, AuthService $authService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }
}
