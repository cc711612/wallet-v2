<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Socials;

use App\Domain\Auth\Services\AuthService;
use App\Domain\Social\Services\SocialService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Socials\SocialBindRequest;
use App\Http\Requests\Apis\Socials\SocialCheckBindRequest;
use App\Http\Requests\Apis\Socials\SocialUnBindRequest;
use App\Http\Resources\Socials\SocialCheckBindResource;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class SocialController extends ApiController
{
    /**
     * 檢查第三方帳號綁定狀態。
     *
     * @param  SocialCheckBindRequest  $request
     * @param  SocialService  $socialService
     * @param  AuthService  $authService
     * @return JsonResponse
     */
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
     *
     * @param  SocialBindRequest  $request
     * @param  SocialService  $socialService
     * @return JsonResponse
     */
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
     *
     * @param  SocialUnBindRequest  $request
     * @param  SocialService  $socialService
     * @return JsonResponse
     */
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
