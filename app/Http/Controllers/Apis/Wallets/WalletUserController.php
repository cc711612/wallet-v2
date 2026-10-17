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
use Illuminate\Http\Request;
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
    #[Response(403, '只能更新自己的成員資料', type: 'array{status: false, code: 403, message: string, data: array<string,mixed>|object}')]
    public function update(int $wallet_users_id, WalletUserUpdateRequest $request, WalletUserService $walletUserService): JsonResponse
    {
        // 只能修改自己（middleware 驗證過的帳本成員身分）
        $ownWalletUserIds = array_map(
            static fn (mixed $walletUser): int => (int) data_get($walletUser, 'id', 0),
            array_values((array) $request->input('wallet_user', []))
        );
        if (! in_array($wallet_users_id, $ownWalletUserIds, true)) {
            return $this->response()->errorForbidden('只能更新自己的成員資料');
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $walletUserService->update($wallet_users_id, $validated);

        return $this->response()->success();
    }

    /**
     * 刪除帳本成員。
     */
    #[Response(200, '刪除帳本成員成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    #[Response(403, '非帳本管理員', type: 'array{status: false, code: 403, message: string, data: array<string,mixed>|object}')]
    public function destroy(int $wallet, int $wallet_user_id, Request $request, WalletUserService $walletUserService): JsonResponse
    {
        // 只有該帳本的 admin 可以刪除成員
        if ((int) data_get($request->input('wallet_user', []), $wallet.'.is_admin', 0) !== 1) {
            return $this->response()->errorForbidden('非帳本管理員');
        }

        $walletUserService->destroy($wallet, $wallet_user_id);

        return $this->response()->success();
    }
}
