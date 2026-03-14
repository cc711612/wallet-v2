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
    /**
     * 一般帳密登入。
     */
    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $validated = $request->validated();

            $payload = array_merge($validated, [
                'type' => $request->input('type'),
                'jwt_token' => (string) ($request->bearerToken() ?? ''),
                'users' => [
                    'ip' => (string) $request->ip(),
                    'agent' => (string) ($request->userAgent() ?? ''),
                ],
            ]);

            return $this->response()->success(new AuthLoginResource($authService->login($payload)));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 保活快取檢查。
     */
    public function cache(AuthService $authService): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => null,
            'data' => $authService->cache(),
        ]);
    }

    /**
     * 第三方登入。
     */
    public function thirdPartyLogin(ThirdPartyLoginRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $validated = $request->validated();

            return $this->response()->success(new AuthLoginResource($authService->thirdPartyLogin($validated)));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }
}
