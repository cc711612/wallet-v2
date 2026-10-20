<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Services\VerifyApiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApi
{
    /**
     * @return void
     */
    public function __construct(private VerifyApiService $verifyApiService) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = (string) ($request->bearerToken() ?? '');
        if ($bearerToken !== '') {
            $jwtUser = $this->verifyApiService->resolveUserByJwt($bearerToken);
            if ($jwtUser !== null) {
                $request->merge(['user' => $jwtUser]);

                return $next($request);
            }

            if ($this->isBlockRoute($request)) {
                // 登出：JWT 無效時不報錯，但清掉 client 送來的 user，避免替他人登出
                $request->merge(['user' => []]);

                return $next($request);
            }

            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請重新登入',
                'data' => [],
            ], 401);
        }

        // member_token 只接受字串；陣列（member_token[]=x）、數字等一律視為沒帶有效憑證，避免 Array to string 500
        $rawMemberToken = $request->input('member_token', '');
        $memberToken = is_string($rawMemberToken) ? $rawMemberToken : '';
        if ($memberToken === '' && ! $this->isBlockRoute($request)) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請帶入 member_token',
                'data' => [],
            ], 401);
        }

        if ($this->isBlockRoute($request)) {
            // 登出：沒帶或帶了無效的 member_token 時維持 200 no-op；
            // user 一律以伺服器端解析結果覆寫，絕不信任 client 送來的 user
            $request->merge(['user' => $this->verifyApiService->resolveUserByToken($memberToken) ?? []]);

            return $next($request);
        }

        $user = $this->verifyApiService->resolveUserByToken($memberToken);
        if ($user === null) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請重新登入',
                'data' => [],
            ], 401);
        }

        $request->merge(['user' => $user]);

        return $next($request);
    }

    private function isBlockRoute(Request $request): bool
    {
        return $request->routeIs(['api.auth.logout']);
    }
}
