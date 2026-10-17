<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use UnexpectedValueException;

class JwtTokenService
{
    private const MIN_SECRET_LENGTH = 32;

    /**
     * @param  array<string, mixed>  $user
     */
    public function makeUserJwt(array $user): string
    {
        return $this->encode([
            'iss' => (string) config('app.url'),
            'aud' => 'https://easysplit.usongrat.tw',
            'iat' => now()->timestamp,
            'exp' => now()->addYear()->timestamp,
            'nbf' => now()->timestamp,
            'user' => [
                'id' => Crypt::encryptString((string) ($user['id'] ?? '')),
                'account' => (string) ($user['account'] ?? ''),
                'name' => (string) ($user['name'] ?? ''),
                'created_at' => (string) ($user['created_at'] ?? ''),
                'updated_at' => (string) ($user['updated_at'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $walletUser
     */
    public function makeWalletUserJwt(array $walletUser): string
    {
        return $this->encode([
            'iss' => (string) config('app.url'),
            'aud' => 'https://easysplit.usongrat.tw',
            'iat' => now()->timestamp,
            'exp' => now()->addYear()->timestamp,
            'nbf' => now()->timestamp,
            'wallet_user' => [
                'id' => Crypt::encryptString((string) ($walletUser['id'] ?? '')),
                'name' => (string) ($walletUser['name'] ?? ''),
                'created_at' => (string) ($walletUser['created_at'] ?? ''),
                'updated_at' => (string) ($walletUser['updated_at'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $headerEncoded = $this->base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $secret = $this->secret();
        if ($secret === null) {
            // fail-closed：金鑰未設定或太短時不簽發（非 RuntimeException 本身，clientMessage 不會回傳原文）
            throw new UnexpectedValueException('JWT_SECRET 未設定或長度不足，無法簽發 JWT');
        }

        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $secret, true);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * 驗證並解析 JWT：只接受 HS256、簽章必須正確、必須帶 exp 且未過期、nbf 已生效。
     * 任何一項不符合都回傳 null（呼叫端一律視為未登入）。
     *
     * @return array<string, mixed>|null
     */
    public function decodeVerified(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $header = $this->decodeJsonSegment($headerEncoded);
        if ($header === null || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $signature = $this->base64UrlDecode($signatureEncoded);
        if ($signature === null) {
            return null;
        }

        $secret = $this->secret();
        if ($secret === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $secret, true);
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = $this->decodeJsonSegment($payloadEncoded);
        if ($payload === null) {
            return null;
        }

        $now = now()->timestamp;
        $leeway = max(0, (int) config('services.jwt.leeway', 60));

        if (! isset($payload['exp']) || ! is_numeric($payload['exp']) || (int) $payload['exp'] + $leeway < $now) {
            return null;
        }

        if (isset($payload['nbf']) && (! is_numeric($payload['nbf']) || (int) $payload['nbf'] - $leeway > $now)) {
            return null;
        }

        return $payload;
    }

    /**
     * 簽章金鑰只接受 JWT_SECRET（至少 32 字元）；未設定或太短回傳 null，簽發與驗證都會拒絕。
     * 不再退回 APP_NAME（APP_NAME 是公開值）。
     */
    private function secret(): ?string
    {
        $secret = (string) config('services.jwt.secret', '');

        return strlen($secret) >= self::MIN_SECRET_LENGTH ? $secret : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonSegment(string $segment): ?array
    {
        $decoded = $this->base64UrlDecode($segment);
        if ($decoded === null) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
