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
    /**
     * 依帳本驗證碼取得成員列表。
     */
    public function index(WalletUserIndexRequest $request, WalletUserService $walletUserService): JsonResponse
    {
        try {
            /** @var array<string, mixed> $validated */
            $validated = $request->validated();

            return $this->response()->success(new WalletUserIndexResource($walletUserService->index((string) $validated['code'])));
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 更新帳本成員資料。
     */
    public function update(int $wallet_users_id, WalletUserUpdateRequest $request, WalletUserService $walletUserService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $walletUserService->update($wallet_users_id, $validated);

        return $this->response()->success();
    }

    /**
     * 刪除帳本成員。
     */
    public function destroy(int $wallet, int $wallet_user_id, WalletUserService $walletUserService): JsonResponse
    {
        $walletUserService->destroy($wallet, $wallet_user_id);

        return $this->response()->success();
    }
}
