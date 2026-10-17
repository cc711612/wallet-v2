<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;
use App\Support\JwtTokenService;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class VerifyApiService
{
    /**
     * @return void
     */
    public function __construct(
        private UserTokenRepositoryInterface $userTokenRepository,
        private JwtTokenService $jwtTokenService,
    ) {}

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
        // 只接受本服務簽發、簽章正確且未過期的 JWT
        $payload = $this->jwtTokenService->decodeVerified($jwt);
        if ($payload === null) {
            return null;
        }

        /** @var array<string, mixed> $userPayload */
        $userPayload = (array) ($payload['user'] ?? []);
        if (empty($userPayload['id'])) {
            return null;
        }

        // user.id 為 Crypt::encryptString 加密值；不再接受以 account 明文登入的 fallback
        try {
            $userId = (int) Crypt::decryptString((string) $userPayload['id']);
        } catch (Throwable) {
            return null;
        }

        if ($userId <= 0) {
            return null;
        }

        return $this->userTokenRepository->findById($userId);
    }
}
