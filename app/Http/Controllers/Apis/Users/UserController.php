<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Users;

use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * 取得使用者社群連結資料。
     */
    public function socials(Request $request, SocialService $socialService): JsonResponse
    {
        $userId = (int) data_get($request->input('user', []), 'id', 0);

        return $this->response()->success($socialService->listForUser($userId));
    }
}
