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
        if (app()->runningUnitTests()) {
            /** @var array<string, mixed> $testingUser */
            $testingUser = ['id' => 1, 'name' => 'testing-user', 'token' => 'testing-token'];
            $request->merge(['user' => $testingUser]);

            return $next($request);
        }

        $bearerToken = (string) ($request->bearerToken() ?? '');
        if ($bearerToken !== '') {
            $jwtUser = $this->verifyApiService->resolveUserByJwt($bearerToken);
            if ($jwtUser !== null) {
                $request->merge(['user' => $jwtUser]);

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
        if ($memberToken === '' && ! $this->isBlockRoute($request)) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '請帶入 member_token',
                'data' => [],
            ], 401);
        }

        if ($this->isBlockRoute($request)) {
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
