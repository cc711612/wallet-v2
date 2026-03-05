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
use App\Http\Resources\WalletDetails\WalletDetailCreatedResource;
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
     * @return JsonResponse
     */
    public function store(StoreWalletDetailRequest $request)
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->validated();

            $walletDetail = WalletDetail::fromPayload($payload);
            $result = $this->walletDetailService->create($walletDetail);

            return $this->response()->success(new WalletDetailCreatedResource($result));
        } catch (WalletDetailBusinessException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->response()->errorInternal('系統忙碌中，請稍後再試');
        }
    }

    public function index(int $wallet, Request $request): JsonResponse
    {
        try {
            $isPersonal = $request->has('is_personal') ? $request->boolean('is_personal') : null;
            $walletUser = (array) $request->input('wallet_user', []);
            $walletUserId = (int) data_get($walletUser, $wallet . '.id', 0);

            return $this->response()->success($this->walletDetailService->index($wallet, $isPersonal, $walletUserId));
        } catch (RuntimeException $exception) {
            return $this->response()->errorInternal($exception->getMessage());
        }
    }

    public function show(int $wallet, int $detail): JsonResponse
    {
        try {
            return $this->response()->success($this->walletDetailService->show($wallet, $detail));
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    public function update(int $wallet, int $detail, UpdateWalletDetailRequest $request): JsonResponse
    {
        $payload = array_merge($request->all(), ['wallet' => $wallet, 'detail' => $detail]);
        $walletDetail = WalletDetail::fromPayload($payload);

        $this->walletDetailService->update($walletDetail, $detail);

        return $this->response()->success();
    }

    public function destroy(int $wallet, int $detail): JsonResponse
    {
        $this->walletDetailService->destroy($wallet, $detail);

        return $this->response()->success();
    }

    public function checkout(int $wallet, CheckoutWalletDetailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->walletDetailService->checkout($wallet, (array) $validated['checkout_id']);

        return $this->response()->success();
    }

    public function uncheckout(int $wallet, UncheckoutWalletDetailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->walletDetailService->uncheckout($wallet, (string) $validated['checkout_at']);

        return $this->response()->success();
    }
}
