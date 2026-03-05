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
     * @param  WalletService  $walletService
     * @return void
     */
    public function __construct(private WalletService $walletService) {}

    /**
     * 帳本列表。
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return $this->response()->success(new WalletIndexResource($this->walletService->index($request->all())));
    }

    /**
     * 綁定訪客帳本。
     *
     * @param  WalletBindRequest  $request
     * @return JsonResponse
     */
    public function bind(WalletBindRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $payload = array_merge($validated, [
                'user' => (array) $request->input('user', []),
            ]);

            $message = $this->walletService->bind($payload);

            return $this->response()->success(null, $message);
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 建立帳本。
     *
     * @param  WalletStoreRequest  $request
     * @return JsonResponse
     */
    public function store(WalletStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'user' => (array) $request->input('user', []),
        ]);

        return $this->response()->success(new WalletStoreResource($this->walletService->store($payload)));
    }

    /**
     * 更新帳本。
     *
     * @param  WalletUpdateRequest  $request
     * @param  int  $wallet
     * @return JsonResponse
     */
    public function update(WalletUpdateRequest $request, int $wallet): JsonResponse
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'user' => (array) $request->input('user', []),
        ]);

        $this->walletService->update($wallet, $payload);

        return $this->response()->success();
    }

    /**
     * 刪除帳本。
     *
     * @param  Request  $request
     * @param  int  $wallet
     * @return JsonResponse
     */
    public function destroy(Request $request, int $wallet): JsonResponse
    {
        $payload = ['user' => (array) $request->input('user', [])];
        $message = $this->walletService->destroy($wallet, $payload);

        return $this->response()->success(null, $message);
    }

    /**
     * 帳本計算結果。
     *
     * @param  int  $wallet
     * @return JsonResponse
     */
    public function calculation(int $wallet): JsonResponse
    {
        return $this->response()->success(new WalletCalculationResource($this->walletService->calculation($wallet)));
    }
}
