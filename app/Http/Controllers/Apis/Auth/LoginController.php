<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Docs\OpenApiSchemas;
use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Auth\LoginRequest;
use App\Http\Requests\Apis\Auth\ThirdPartyLoginRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Throwable;

class LoginController extends ApiController
{
    /**
     * 一般帳密登入。
     */
    #[Response(
        200,
        '登入成功',
        type: OpenApiSchemas::AUTH_LOGIN_RESPONSE
    )]
    #[Response(
        400,
        '登入失敗',
        type: 'array{status: false, code: 400, message: string, data: object}'
    )]
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
    #[Response(
        200,
        '快取檢查成功',
        type: 'array{status: true, code: 200, message: null|string, data: array<int, mixed>}'
    )]
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
    #[Response(
        200,
        '第三方登入成功',
        type: OpenApiSchemas::AUTH_LOGIN_RESPONSE
    )]
    #[Response(
        400,
        '第三方登入失敗',
        type: 'array{status: false, code: 400, message: string, data: object}'
    )]
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
