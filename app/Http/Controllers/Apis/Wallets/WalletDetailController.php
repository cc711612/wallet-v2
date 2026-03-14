<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Domain\Wallet\Services\WalletDetailService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\WalletDetails\CheckoutWalletDetailRequest;
use App\Http\Requests\WalletDetails\StoreWalletDetailRequest;
use App\Http\Requests\WalletDetails\UncheckoutWalletDetailRequest;
use App\Http\Requests\WalletDetails\UpdateWalletDetailRequest;
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
