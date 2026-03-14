<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Users;

use App\Docs\OpenApiSchemas;
use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\ApiController;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * 取得使用者社群連結資料。
     */
    #[Response(200, '取得社群綁定資訊成功', type: 'array{status: true, code: 200, message: string, data: array<int, '.OpenApiSchemas::SOCIAL.'>}')]
    public function socials(Request $request, SocialService $socialService): JsonResponse
    {
        $userId = (int) data_get($request->input('user', []), 'id', 0);

        return $this->response()->success($socialService->listForUser($userId));
    }
}
