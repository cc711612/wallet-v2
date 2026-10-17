<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Repositories\SocialServiceRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class SocialService
{
    /**
     * @return void
     */
    public function __construct(
        private SocialServiceRepositoryInterface $socialRepository,
        private SocialTokenVerifier $socialTokenVerifier,
    ) {}

    /**
     * 檢查社群帳號是否已綁定。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkBind(array $payload): array
    {
        $socialType = (int) ($payload['socialType'] ?? 0);
        $socialTypeValue = (string) ($payload['socialTypeValue'] ?? '');

        // 不再信任 client 傳來的 social id：向 provider 驗證 id_token 後，以 provider 回傳的 sub 為準
        if ((bool) config('services.social_login.verify_token', true)) {
            $verifiedValue = $this->socialTokenVerifier->verify($socialType, (string) ($payload['id_token'] ?? ''));
            if ($verifiedValue === null || ($socialTypeValue !== '' && ! hash_equals($verifiedValue, $socialTypeValue))) {
                throw new RuntimeException('第三方登入驗證失敗');
            }

            $socialTypeValue = $verifiedValue;
        }

        $social = $this->socialRepository->findByTypeAndValue($socialType, $socialTypeValue);
        $bindToken = Str::random(12);

        if ($social !== null && (int) ($social['user_id'] ?? 0) > 0) {
            Cache::put(
                sprintf('auth.thirdParty.%s.%s', $socialType, $bindToken),
                ['user_id' => (int) $social['user_id']],
                now()->addMinutes(5)
            );

            return ['action' => 'bind', 'token' => $bindToken];
        }

        $upserted = $this->socialRepository->updateOrCreateByTypeAndValue($socialType, $socialTypeValue, $payload);
        Cache::put('registerByToken_'.$bindToken, $upserted, now()->addMinutes(5));

        return [
            'action' => 'not bind',
            'token' => $bindToken,
        ];
    }

    /**
     * 綁定社群帳號到目前使用者。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function bind(array $payload): array
    {
        $token = (string) ($payload['token'] ?? '');
        $userId = (int) data_get($payload, 'user.id', 0);
        $social = Cache::pull('registerByToken_'.$token);
        if (! is_array($social) || (int) ($social['id'] ?? 0) <= 0 || $userId <= 0) {
            throw new RuntimeException('token is invalid');
        }

        $this->socialRepository->bindSocialToUser((int) $social['id'], $userId);

        return ['bound' => true];
    }

    /**
     * 解除社群綁定。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function unBind(array $payload): array
    {
        $userId = (int) data_get($payload, 'user.id', 0);
        $socialType = (int) ($payload['socialType'] ?? 0);
        if ($userId <= 0 || $socialType <= 0) {
            return ['unbound' => false];
        }

        $this->socialRepository->unbindSocialByTypeAndUser($socialType, $userId);

        return ['unbound' => true];
    }

    /**
     * 取得使用者第三方綁定列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->socialRepository->listSocialsByUserId($userId);
    }
}
