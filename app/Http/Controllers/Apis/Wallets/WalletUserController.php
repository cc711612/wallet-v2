<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Domain\Wallet\Services\WalletUserService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\WalletUserIndexRequest;
use App\Http\Requests\Apis\Wallets\WalletUserUpdateRequest;
use App\Http\Resources\Wallets\WalletUserIndexResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Throwable;

class WalletUserController extends ApiController
{
    /**
     * 依帳本驗證碼取得成員列表。
     */
    #[Response(200, '取得帳本成員成功', type: 'array{status: true, code: 200, message: string, data: array{wallet: array<string,mixed>}}')]
    #[Response(400, '取得帳本成員失敗', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
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
    #[Response(200, '更新帳本成員成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
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
    #[Response(200, '刪除帳本成員成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function destroy(int $wallet, int $wallet_user_id, WalletUserService $walletUserService): JsonResponse
    {
        $walletUserService->destroy($wallet, $wallet_user_id);

        return $this->response()->success();
    }
}
