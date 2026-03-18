<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Auth\RegisterByTokenRequest;
use App\Http\Requests\Apis\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    /**
     * 註冊新使用者。
     */
    #[Response(200, '註冊成功', type: 'array{status: bool, code: int, message: string, data: array{user: array<string,mixed>, token: string}}')]
    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }

    /**
     * 透過 token 註冊使用者。
     */
    #[Response(200, 'Token 註冊成功', type: 'array{status: bool, code: int, message: string, data: array{user: array<string,mixed>, token: string}}')]
    public function registerByToken(RegisterByTokenRequest $request, AuthService $authService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($authService->register($validated)));
    }
}
