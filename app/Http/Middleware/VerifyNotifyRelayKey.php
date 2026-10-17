<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 驗證內部推播轉發入口的共享密鑰；密鑰未設定時 fail-closed。
 */
class VerifyNotifyRelayKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('bot.line.notify_relay_key', '');
        $given = (string) $request->header('X-Notify-Relay-Key', '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            Log::warning('VerifyNotifyRelayKey: 共享密鑰驗證失敗或未設定');

            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => '驗證失敗',
                'data' => [],
            ], 401);
        }

        return $next($request);
    }
}
