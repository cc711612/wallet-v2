<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletMemberTokenRepositoryInterface;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class VerifyWalletMemberService
{
    /**
     * @return void
     */
    public function __construct(
        private UserTokenRepositoryInterface $userTokenRepository,
        private WalletMemberTokenRepositoryInterface $walletMemberTokenRepository
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolveWalletUserByToken(string $token, int $walletId): ?array
    {
        if ($token === '') {
            return null;
        }

        $walletUser = $this->walletMemberTokenRepository->findByToken($token);
        if ($walletUser === null) {
            return null;
        }

        if ((int) ($walletUser['wallet_id'] ?? 0) !== $walletId) {
            return null;
        }

        return $walletUser;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveWalletUsersByJwt(string $jwt): array
    {
        $payload = $this->decodeJwtPayload($jwt);
        if ($payload === []) {
            return [];
        }

        /** @var array<string, mixed> $userPayload */
        $userPayload = (array) ($payload['user'] ?? []);
        if (! empty($userPayload['id'])) {
            try {
                $userId = (int) Crypt::decryptString((string) $userPayload['id']);
                $items = $this->walletMemberTokenRepository->listByUserId($userId);
                if ($items !== []) {
                    return $this->keyByWalletId($items);
                }
            } catch (Throwable) {
                // Continue next path.
            }
        }

        if (! empty($userPayload['account'])) {
            $user = $this->userTokenRepository->findByAccount((string) $userPayload['account']);
            if ($user !== null) {
                $items = $this->walletMemberTokenRepository->listByUserId((int) $user['id']);
                if ($items !== []) {
                    return $this->keyByWalletId($items);
                }
            }
        }

        /** @var array<string, mixed> $walletUserPayload */
        $walletUserPayload = (array) ($payload['wallet_user'] ?? []);
        if (! empty($walletUserPayload['id'])) {
            try {
                $walletUserId = (int) Crypt::decryptString((string) $walletUserPayload['id']);
                $item = $this->walletMemberTokenRepository->findByWalletUserId($walletUserId);
                if ($item !== null) {
                    return $this->keyByWalletId([$item]);
                }
            } catch (Throwable) {
                return [];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function keyByWalletId(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $walletId = (int) ($item['wallet_id'] ?? 0);
            if ($walletId > 0) {
                $result[$walletId] = $item;
            }
        }

        return $result;
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
