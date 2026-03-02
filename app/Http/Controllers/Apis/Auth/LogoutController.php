<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Auth;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends ApiController
{
    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request);

        return $this->response()->success();
    }
}
