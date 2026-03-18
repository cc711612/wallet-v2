<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Docs\OpenApiSchemas;
use App\Domain\Wallet\Services\WalletService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\WalletBindRequest;
use App\Http\Requests\Apis\Wallets\WalletStoreRequest;
use App\Http\Requests\Apis\Wallets\WalletUpdateRequest;
use App\Http\Resources\Wallets\WalletCalculationResource;
use App\Http\Resources\Wallets\WalletIndexResource;
use App\Http\Resources\Wallets\WalletStoreResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WalletController extends ApiController
{
    /**
     * @return void
     */
    public function __construct(private WalletService $walletService) {}

    /**
     * 帳本列表。
     */
    #[Response(
        200,
        '取得帳本列表成功',
        type: OpenApiSchemas::WALLET_INDEX_RESPONSE
    )]
    public function index(Request $request): JsonResponse
    {
        return $this->response()->success(new WalletIndexResource($this->walletService->index($request->all())));
    }

    /**
     * 綁定訪客帳本。
     */
    #[Response(
        200,
        '綁定成功',
        type: 'array{status: true, code: 200, message: string, data: array<string, mixed>|object}'
    )]
    #[Response(
        400,
        '綁定失敗',
        type: 'array{status: false, code: 400, message: string, data: array<string, mixed>|object}'
    )]
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
     */
    #[Response(
        200,
        '建立帳本成功',
        type: 'array{status: true, code: 200, message: string, data: array{wallet: array<string, mixed>}}'
    )]
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
     */
    #[Response(
        200,
        '更新帳本成功',
        type: 'array{status: true, code: 200, message: string, data: array<string, mixed>|object}'
    )]
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
     */
    #[Response(
        200,
        '刪除帳本成功',
        type: 'array{status: true, code: 200, message: string, data: array<string, mixed>|object}'
    )]
    public function destroy(Request $request, int $wallet): JsonResponse
    {
        $payload = ['user' => (array) $request->input('user', [])];
        $message = $this->walletService->destroy($wallet, $payload);

        return $this->response()->success(null, $message);
    }

    /**
     * 帳本計算結果。
     */
    #[Response(
        200,
        '帳本計算成功',
        type: 'array{status: true, code: 200, message: string, data: array{wallet: array<string, mixed>}}'
    )]
    public function calculation(int $wallet): JsonResponse
    {
        return $this->response()->success(new WalletCalculationResource($this->walletService->calculation($wallet)));
    }
}
