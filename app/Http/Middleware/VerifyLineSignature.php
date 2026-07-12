<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyLineSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channelSecret = (string) config('bot.line.channel_secret', '');
        if ($channelSecret === '') {
            Log::error('VerifyLineSignature: LINE channel_secret 未設定，拒絕請求');

            return $this->unauthorized();
        }

        $signature = (string) $request->header('X-Line-Signature', '');
        if ($signature === '') {
            Log::error('VerifyLineSignature: 缺少 X-Line-Signature header');

            return $this->unauthorized();
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $channelSecret, true));

        if (! hash_equals($expectedSignature, $signature)) {
            Log::error('VerifyLineSignature: 簽章驗證失敗');

            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'status' => false,
            'code' => 401,
            'message' => 'LINE 簽章驗證失敗',
            'data' => [],
        ], 401);
    }
}
