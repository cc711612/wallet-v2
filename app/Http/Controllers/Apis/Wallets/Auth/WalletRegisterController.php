<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets\Auth;

use App\Domain\Wallet\Services\WalletAuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\Auth\WalletRegisterBatchRequest;
use App\Http\Requests\Apis\Wallets\Auth\WalletRegisterRequest;
use App\Http\Resources\Auth\AuthLoginResource;
use Illuminate\Http\JsonResponse;

class WalletRegisterController extends ApiController
{
    /**
     * 註冊單一帳本成員。
     */
    public function register(WalletRegisterRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($walletAuthService->register($validated)));
    }

    /**
     * 批次註冊帳本成員。
     */
    public function registerBatch(WalletRegisterBatchRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $walletAuthService->registerBatch($validated);

        return $this->response()->success();
    }
}
