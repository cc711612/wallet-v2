<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletMemberTokenRepositoryInterface;
use App\Support\JwtTokenService;

class VerifyWalletMemberService
{
    /**
     * @return void
     */
    public function __construct(
        private WalletMemberTokenRepositoryInterface $walletMemberTokenRepository,
        private JwtTokenService $jwtTokenService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolveWalletUserByToken(string $token, ?int $walletId): ?array
    {
        if ($token === '') {
            return null;
        }

        $walletUser = $this->walletMemberTokenRepository->findByToken($token);
        if ($walletUser === null) {
            return null;
        }

        // $walletId 為 null 表示路由不限定帳本（例如 device），否則 token 必須屬於該帳本
        if ($walletId !== null && (int) ($walletUser['wallet_id'] ?? 0) !== $walletId) {
            return null;
        }

        return $walletUser;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveWalletUsersByJwt(string $jwt): array
    {
        // 只接受本服務簽發、簽章正確且未過期的 JWT
        $payload = $this->jwtTokenService->decodeVerified($jwt);
        if ($payload === null) {
            return [];
        }

        /** @var array<string, mixed> $userPayload */
        $userPayload = (array) ($payload['user'] ?? []);
        if (! empty($userPayload['id'])) {
            $userId = $this->jwtTokenService->decryptSubject(JwtTokenService::SUBJECT_USER, (string) $userPayload['id']);
            if ($userId === null) {
                return [];
            }

            $items = $this->walletMemberTokenRepository->listByUserId($userId);
            if ($items !== []) {
                return $this->keyByWalletId($items);
            }
        }

        /** @var array<string, mixed> $walletUserPayload */
        $walletUserPayload = (array) ($payload['wallet_user'] ?? []);
        if (! empty($walletUserPayload['id'])) {
            $walletUserId = $this->jwtTokenService->decryptSubject(JwtTokenService::SUBJECT_WALLET_USER, (string) $walletUserPayload['id']);
            if ($walletUserId === null) {
                return [];
            }

            $item = $this->walletMemberTokenRepository->findByWalletUserId($walletUserId);
            if ($item !== null) {
                return $this->keyByWalletId([$item]);
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
}
