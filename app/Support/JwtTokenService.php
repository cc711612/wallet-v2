<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

class JwtTokenService
{
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
        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, (string) config('app.name', 'Laravel'), true);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
