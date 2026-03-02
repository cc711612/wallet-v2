<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Auth\LoginRequest;
use App\Http\Requests\Apis\Auth\ThirdPartyLoginRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Illuminate\Http\JsonResponse;
use Throwable;

class LoginController extends ApiController
{
    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $validated = $request->validated();

            return $this->response()->success(new AuthLoginResource($authService->login($validated)));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    public function cache(AuthService $authService): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => null,
            'data' => $authService->cache(),
        ]);
    }

    public function thirdPartyLogin(ThirdPartyLoginRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $validated = $request->validated();

            return $this->response()->success(new AuthLoginResource($authService->login($validated)));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }
}
