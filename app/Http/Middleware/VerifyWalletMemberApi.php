<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Wallet\Services\VerifyWalletMemberService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWalletMemberApi
{
    /**
     * @return void
     */
    public function __construct(private VerifyWalletMemberService $verifyWalletMemberService) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            $walletId = (int) $request->route('wallet', 1);

            /** @var array<int, array<string, mixed>> $testingWalletUser */
            $testingWalletUser = [
                $walletId => ['id' => 100, 'wallet_id' => $walletId, 'name' => 'testing-member', 'is_admin' => 1],
            ];

            $request->merge(['wallet_user' => $testingWalletUser, 'member_token' => 'testing-member-token']);

            return $next($request);
        }

        $walletId = (int) $request->route('wallet', 1);

        $bearerToken = (string) ($request->bearerToken() ?? '');
        if ($bearerToken !== '') {
            $walletUsers = $this->verifyWalletMemberService->resolveWalletUsersByJwt($bearerToken);
            if ($walletUsers !== []) {
                $request->merge(['wallet_user' => $walletUsers]);

                return $next($request);
            }

            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請重新登入',
                'data' => [],
            ], 401);
        }

        $memberToken = (string) ($request->input('member_token', ''));
        if ($memberToken === '') {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請帶入 member_token',
                'data' => [],
            ], 401);
        }

        $walletUserRecord = $this->verifyWalletMemberService->resolveWalletUserByToken($memberToken, $walletId);
        if ($walletUserRecord === null) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請重新登入',
                'data' => [],
            ], 401);
        }

        /** @var array<int, array<string, mixed>> $walletUser */
        $walletUser = [
            $walletId => $walletUserRecord,
        ];

        $request->merge([
            'wallet_user' => $walletUser,
            'member_token' => $memberToken,
        ]);

        return $next($request);
    }
}
