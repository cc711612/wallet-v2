<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class VerifyApiService
{
    /**
     * @return void
     */
    public function __construct(private UserTokenRepositoryInterface $userTokenRepository) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolveUserByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->userTokenRepository->findByToken($token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveUserByJwt(string $jwt): ?array
    {
        $payload = $this->decodeJwtPayload($jwt);
        if ($payload === []) {
            return null;
        }

        /** @var array<string, mixed> $userPayload */
        $userPayload = (array) ($payload['user'] ?? []);
        if ($userPayload === []) {
            return null;
        }

        if (! empty($userPayload['id'])) {
            try {
                $decrypted = Crypt::decryptString((string) $userPayload['id']);
                $userId = (int) $decrypted;
                $user = $this->userTokenRepository->findById($userId);
                if ($user !== null) {
                    return $user;
                }
            } catch (Throwable) {
                // Fallback to account below.
            }
        }

        if (! empty($userPayload['account'])) {
            $user = $this->userTokenRepository->findByAccount((string) $userPayload['account']);
            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payloadB64 = str_replace(['-', '_'], ['+', '/'], $parts[1]);
        $padding = strlen($payloadB64) % 4;
        if ($padding > 0) {
            $payloadB64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($payloadB64, true);
        if ($decoded === false) {
            return [];
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($decoded, true);

        return $payload ?? [];
    }
}
