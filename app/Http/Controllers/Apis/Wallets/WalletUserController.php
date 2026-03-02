<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Domain\Wallet\Services\WalletUserService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\WalletUserIndexRequest;
use App\Http\Requests\Apis\Wallets\WalletUserUpdateRequest;
use App\Http\Resources\Wallets\WalletUserIndexResource;
use Illuminate\Http\JsonResponse;
use Throwable;

class WalletUserController extends ApiController
{
    public function index(WalletUserIndexRequest $request, WalletUserService $walletUserService): JsonResponse
    {
        try {
            $validated = $request->validated();

            return $this->response()->success(new WalletUserIndexResource($walletUserService->index((string) $validated['code'])));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    public function update(int $wallet_users_id, WalletUserUpdateRequest $request, WalletUserService $walletUserService): JsonResponse
    {
        $validated = $request->validated();

        $walletUserService->update($wallet_users_id, $validated);

        return $this->response()->success();
    }

    public function destroy(int $wallet, int $wallet_user_id, WalletUserService $walletUserService): JsonResponse
    {
        $walletUserService->destroy($wallet, $wallet_user_id);

        return $this->response()->success();
    }
}
