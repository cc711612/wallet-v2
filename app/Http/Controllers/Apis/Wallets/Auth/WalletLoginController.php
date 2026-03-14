<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Wallets\Auth;

use App\Domain\Wallet\Services\WalletAuthService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Wallets\Auth\WalletLoginRequest;
use App\Http\Requests\Apis\Wallets\Auth\WalletTokenLoginRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class WalletLoginController extends ApiController
{
    /**
     * 以帳本代碼與名稱登入帳本。
     *
     * @param  WalletLoginRequest  $request
     * @param  WalletAuthService  $walletAuthService
     * @return JsonResponse
     */
    public function login(WalletLoginRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        try {
            /** @var array<string, mixed> $validated */
            $validated = $request->validated();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => null,
                'data' => $walletAuthService->login($validated),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'code' => $exception->getCode() === 401 ? 401 : 400,
                'message' => $exception->getMessage(),
                'data' => (object) [],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => '系統錯誤',
                'data' => (object) [],
            ]);
        }
    }

    /**
     * 以成員 token 登入帳本。
     *
     * @param  WalletTokenLoginRequest  $request
     * @param  WalletAuthService  $walletAuthService
     * @return JsonResponse
     */
    public function token(WalletTokenLoginRequest $request, WalletAuthService $walletAuthService): JsonResponse
    {
        try {
            /** @var array<string, mixed> $validated */
            $validated = $request->validated();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => null,
                'data' => $walletAuthService->token($validated),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'code' => $exception->getCode() === 401 ? 401 : 400,
                'message' => $exception->getMessage(),
                'data' => (object) [],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => '系統錯誤',
                'data' => (object) [],
            ]);
        }
    }
}
