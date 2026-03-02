<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Domain\Wallet\Services\WalletService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\WalletBindRequest;
use App\Http\Requests\Apis\Wallets\WalletStoreRequest;
use App\Http\Requests\Apis\Wallets\WalletUpdateRequest;
use App\Http\Resources\Wallets\WalletCalculationResource;
use App\Http\Resources\Wallets\WalletIndexResource;
use App\Http\Resources\Wallets\WalletStoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function index(Request $request, WalletService $walletService): JsonResponse
    {
        return $this->response()->success(new WalletIndexResource($walletService->index($request->all())));
    }

    public function bind(WalletBindRequest $request, WalletService $walletService): JsonResponse
    {
        $validated = $request->validated();

        $message = $walletService->bind($validated);

        return $this->response()->success(null, $message);
    }

    public function store(WalletStoreRequest $request, WalletService $walletService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new WalletStoreResource($walletService->store($validated)));
    }

    public function update(WalletUpdateRequest $request, int $wallet, WalletService $walletService): JsonResponse
    {
        $validated = $request->validated();

        $walletService->update($wallet, $validated);

        return $this->response()->success();
    }

    public function destroy(int $wallet, WalletService $walletService): JsonResponse
    {
        $message = $walletService->destroy($wallet);

        return $this->response()->success(null, $message);
    }

    public function calculation(int $wallet, WalletService $walletService): JsonResponse
    {
        return $this->response()->success(new WalletCalculationResource($walletService->calculation($wallet)));
    }
}
