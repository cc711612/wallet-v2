<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Enums\SocialTypeEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 向第三方 provider 驗證 client 送來的 id_token，取得 provider 端的使用者 id（sub）。
 * 驗證失敗、provider 未設定或不支援時一律回傳 null（fail-closed）。
 */
class SocialTokenVerifier
{
    public function verify(int $socialType, string $idToken): ?string
    {
        if ($idToken === '') {
            return null;
        }

        try {
            return match ($socialType) {
                SocialTypeEnum::SOCIAL_TYPE_LINE->value, SocialTypeEnum::SOCIAL_TYPE_LINE_AT->value => $this->verifyLine($idToken),
                SocialTypeEnum::SOCIAL_TYPE_GOOGLE->value => $this->verifyGoogle($idToken),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning('SocialTokenVerifier: provider 驗證失敗', ['social_type' => $socialType, 'error' => $exception::class]);

            return null;
        }
    }

    /**
     * LINE Login：POST /oauth2/v2.1/verify（id_token + client_id），LINE 會驗簽、aud、exp。
     */
    private function verifyLine(string $idToken): ?string
    {
        $channelId = (string) config('services.line_login.channel_id', '');
        if ($channelId === '') {
            Log::error('SocialTokenVerifier: LINE_LOGIN_CHANNEL_ID 未設定');

            return null;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post('https://api.line.me/oauth2/v2.1/verify', [
                'id_token' => $idToken,
                'client_id' => $channelId,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $sub = (string) data_get($response->json(), 'sub', '');
        $aud = (string) data_get($response->json(), 'aud', '');

        return $sub !== '' && $aud === $channelId ? $sub : null;
    }

    /**
     * Google：GET oauth2.googleapis.com/tokeninfo，並比對 aud 是否為本服務的 client id。
     */
    private function verifyGoogle(string $idToken): ?string
    {
        /** @var array<int, string> $clientIds */
        $clientIds = (array) config('services.google_login.client_ids', []);
        if ($clientIds === []) {
            Log::error('SocialTokenVerifier: GOOGLE_LOGIN_CLIENT_IDS 未設定');

            return null;
        }

        $response = Http::timeout(5)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (! $response->successful()) {
            return null;
        }

        $sub = (string) data_get($response->json(), 'sub', '');
        $aud = (string) data_get($response->json(), 'aud', '');
        $iss = (string) data_get($response->json(), 'iss', '');
        $exp = (int) data_get($response->json(), 'exp', 0);

        if (
            $sub === ''
            || ! in_array($aud, $clientIds, true)
            || ! in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)
            || $exp < now()->timestamp
        ) {
            return null;
        }

        return $sub;
    }
}
