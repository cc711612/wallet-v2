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
use RuntimeException;

class WalletController extends ApiController
{
    /**
     * 帳本列表。
     *
     * @param  Request  $request
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function index(Request $request, WalletService $walletService): JsonResponse
    {
        return $this->response()->success(new WalletIndexResource($walletService->index($request->all())));
    }

    /**
     * 綁定訪客帳本。
     *
     * @param  WalletBindRequest  $request
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function bind(WalletBindRequest $request, WalletService $walletService): JsonResponse
    {
        try {
            $validated = $request->validated();
            $payload = array_merge($validated, [
                'user' => (array) $request->input('user', []),
            ]);

            $message = $walletService->bind($payload);

            return $this->response()->success(null, $message);
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 建立帳本。
     *
     * @param  WalletStoreRequest  $request
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function store(WalletStoreRequest $request, WalletService $walletService): JsonResponse
    {
        $validated = $request->validated();

        return $this->response()->success(new WalletStoreResource($walletService->store($validated)));
    }

    /**
     * 更新帳本。
     *
     * @param  WalletUpdateRequest  $request
     * @param  int  $wallet
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function update(WalletUpdateRequest $request, int $wallet, WalletService $walletService): JsonResponse
    {
        $validated = $request->validated();

        $walletService->update($wallet, $validated);

        return $this->response()->success();
    }

    /**
     * 刪除帳本。
     *
     * @param  int  $wallet
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function destroy(int $wallet, WalletService $walletService): JsonResponse
    {
        $message = $walletService->destroy($wallet);

        return $this->response()->success(null, $message);
    }

    /**
     * 帳本計算結果。
     *
     * @param  int  $wallet
     * @param  WalletService  $walletService
     * @return JsonResponse
     */
    public function calculation(int $wallet, WalletService $walletService): JsonResponse
    {
        return $this->response()->success(new WalletCalculationResource($walletService->calculation($wallet)));
    }
}
