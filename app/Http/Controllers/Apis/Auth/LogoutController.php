<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends ApiController
{
    /**
     * 登出目前使用者。
     */
    #[Response(200, '登出成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request);

        return $this->response()->success();
    }
}
