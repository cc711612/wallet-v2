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
    /**
     * 註冊新使用者。
     */
    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }

    /**
     * 透過 token 註冊使用者。
     */
    public function registerByToken(RegisterByTokenRequest $request, AuthService $authService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }
}
