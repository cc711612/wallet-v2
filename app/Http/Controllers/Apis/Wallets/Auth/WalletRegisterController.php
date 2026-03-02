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
    public function register(WalletRegisterRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new AuthLoginResource($walletAuthService->register($validated)));
    }

    public function registerBatch(WalletRegisterBatchRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        $validated = $request->validated();

        $walletAuthService->registerBatch($validated);

        return $this->response()->success();
    }
}
