<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Docs\OpenApiSchemas;
use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Domain\Wallet\Services\WalletDetailService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\WalletDetails\CheckoutWalletDetailRequest;
use App\Http\Requests\WalletDetails\StoreWalletDetailRequest;
use App\Http\Requests\WalletDetails\UncheckoutWalletDetailRequest;
use App\Http\Requests\WalletDetails\UpdateWalletDetailRequest;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class WalletDetailController extends ApiController
{
    /**
     * @return void
     */
    public function __construct(private WalletDetailService $walletDetailService) {}

    /**
     * 建立帳本明細。
     */
    #[Response(200, '建立明細成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    #[Response(400, '建立明細失敗', type: 'array{status:false, code:int, message:string, data:array<string,mixed>|object}')]
    public function store(StoreWalletDetailRequest $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->validated();

            $walletDetail = WalletDetail::fromPayload($payload);
            $this->walletDetailService->create($walletDetail);

            return $this->response()->success();
        } catch (WalletDetailBusinessException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    /**
     * 取得帳本明細列表。
     */
    #[Response(200, '取得明細列表成功', type: OpenApiSchemas::WALLET_DETAIL_INDEX_RESPONSE)]
    #[Response(500, '取得明細列表失敗', type: 'array{status:false, code:500, message:string, data:array<string,mixed>|object}')]
    public function index(int $wallet, Request $request): JsonResponse
    {
        try {
            $isPersonal = $request->has('is_personal') ? $request->boolean('is_personal') : null;
            $walletUser = (array) $request->input('wallet_user', []);
            $walletUserId = (int) data_get($walletUser, $wallet.'.id', 0);

            return $this->response()->success($this->walletDetailService->index($wallet, $isPersonal, $walletUserId));
        } catch (RuntimeException $exception) {
            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    /**
     * 取得單筆帳本明細。
     */
    #[Response(200, '取得明細成功', type: 'array{status:true, code:200, message:string, data:array{wallet: array{id:int, wallet_detail: array<string,mixed>}}}')]
    #[Response(400, '取得明細失敗', type: 'array{status:false, code:400, message:string, data:array<string,mixed>|object}')]
    public function show(int $wallet, int $detail): JsonResponse
    {
        try {
            return $this->response()->success($this->walletDetailService->show($wallet, $detail));
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 更新帳本明細。
     */
    #[Response(200, '更新明細成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    #[Response(400, '更新明細失敗', type: 'array{status:false, code:int, message:string, data:array<string,mixed>|object}')]
    public function update(int $wallet, int $detail, UpdateWalletDetailRequest $request): JsonResponse
    {
        try {
            $payload = array_merge($request->validated(), ['wallet' => $wallet, 'detail' => $detail]);
            $walletDetail = WalletDetail::fromPayload($payload);

            $this->walletDetailService->update($walletDetail, $detail);

            return $this->response()->success();
        } catch (WalletDetailBusinessException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    /**
     * 刪除帳本明細。
     */
    #[Response(200, '刪除明細成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    #[Response(400, '刪除明細失敗', type: 'array{status:false, code:int, message:string, data:array<string,mixed>|object}')]
    public function destroy(int $wallet, int $detail, Request $request): JsonResponse
    {
        try {
            $walletUser = (array) data_get($request->input('wallet_user', []), (string) $wallet, []);
            $walletUserId = (int) data_get($walletUser, 'id', 0);
            $isAdmin = (int) data_get($walletUser, 'is_admin', 0) === 1;
            $this->walletDetailService->destroy($wallet, $detail, $walletUserId, $isAdmin);

            return $this->response()->success();
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    /**
     * 結帳帳本明細。
     */
    #[Response(200, '結帳成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    #[Response(400, '結帳失敗', type: 'array{status:false, code:int, message:string, data:array<string,mixed>|object}')]
    public function checkout(int $wallet, CheckoutWalletDetailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $this->walletDetailService->checkout(
                $wallet,
                (array) $validated['checkout_id'],
                (int) $validated['wallet_user_id']
            );

            return $this->response()->success();
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    /**
     * 取消結帳帳本明細。
     */
    #[Response(200, '取消結帳成功', type: 'array{status:true, code:200, message:string, data:array<string,mixed>|object}')]
    #[Response(400, '取消結帳失敗', type: 'array{status:false, code:int, message:string, data:array<string,mixed>|object}')]
    public function uncheckout(int $wallet, UncheckoutWalletDetailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $this->walletDetailService->uncheckout(
                $wallet,
                (string) $validated['checkout_at'],
                (int) $validated['wallet_user_id']
            );

            return $this->response()->success();
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal($exception->getMessage());
        }
    }
}
