<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets\Auth;

use App\Domain\Wallet\Services\WalletAuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\Auth\WalletRegisterBatchRequest;
use App\Http\Requests\Apis\Wallets\Auth\WalletRegisterRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class WalletRegisterController extends ApiController
{
    /**
     * 註冊單一帳本成員。
     */
    #[Response(200, '註冊帳本成員成功', type: 'array{status: bool, code: int, message: string, data: array{user: array<string,mixed>, token: string}}')]
    public function register(WalletRegisterRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($walletAuthService->register($validated)));
    }

    /**
     * 批次註冊帳本成員。
     */
    #[Response(200, '批次註冊成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function registerBatch(WalletRegisterBatchRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $walletAuthService->registerBatch($validated);

        return $this->response()->success();
    }
}
