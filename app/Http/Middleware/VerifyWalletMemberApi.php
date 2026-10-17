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
        $walletId = (int) $request->route('wallet', 1);

        $bearerToken = (string) ($request->bearerToken() ?? '');
        if ($bearerToken !== '') {
            $walletUsers = $this->verifyWalletMemberService->resolveWalletUsersByJwt($bearerToken);
            if ($walletUsers !== []) {
                // 路由帶 {wallet} 時，必須是自己所屬的帳本
                if ($request->route('wallet') !== null && ! isset($walletUsers[$walletId])) {
                    return response()->json([
                        'status' => false,
                        'code' => 403,
                        'message' => '無此帳本權限',
                        'data' => [],
                    ], 403);
                }

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
