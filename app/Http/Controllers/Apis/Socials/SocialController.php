<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Socials;

use App\Docs\OpenApiSchemas;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Socials\SocialBindRequest;
use App\Http\Requests\Apis\Socials\SocialCheckBindRequest;
use App\Http\Requests\Apis\Socials\SocialUnBindRequest;
use App\Http\Resources\Socials\SocialCheckBindResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class SocialController extends ApiController
{
    /**
     * 檢查第三方帳號綁定狀態。
     */
    #[Response(200, '檢查綁定成功', type: 'array{status: bool, code: int, message: string, data: '.OpenApiSchemas::SOCIAL_CHECK_BIND.'}')]
    #[Response(400, '檢查綁定失敗', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
    public function checkBind(SocialCheckBindRequest $request, SocialService $socialService, AuthService $authService): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $socialService->checkBind($validated);

            if ((string) ($result['action'] ?? '') !== 'bind') {
                return $this->response()->success(new SocialCheckBindResource($result));
            }

            $login = $authService->thirdPartyLogin([
                'provider' => (int) ($validated['socialType'] ?? 0),
                'token' => (string) ($result['token'] ?? ''),
                'users' => [
                    'ip' => (string) $request->ip(),
                    'agent' => (string) ($request->userAgent() ?? ''),
                ],
            ]);

            return $this->response()->success(array_merge($login, ['action' => 'bind']));
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest('登入失敗');
        }
    }

    /**
     * 綁定第三方帳號。
     */
    #[Response(200, '綁定成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    #[Response(400, '綁定失敗', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
    public function bind(SocialBindRequest $request, SocialService $socialService): JsonResponse
    {
        try {
            $validated = $request->validated();
            $payload = array_merge($validated, [
                'user' => (array) $request->input('user', []),
            ]);

            $socialService->bind($payload);

            return $this->response()->success();
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 解除第三方帳號綁定。
     */
    #[Response(200, '解除綁定成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function unBind(SocialUnBindRequest $request, SocialService $socialService): JsonResponse
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'user' => (array) $request->input('user', []),
        ]);

        $socialService->unBind($payload);

        return $this->response()->success();
    }
}
