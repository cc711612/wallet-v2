<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Support\JwtTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AuthService
{
    /**
     * @return void
     */
    public function __construct(
        private AuthServiceRepositoryInterface $authServiceRepository,
        private JwtTokenService $jwtTokenService,
    ) {}

    /**
     * 帳密登入。
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $user = $this->authServiceRepository->findUserByAccount((string) ($credentials['account'] ?? ''));

        if ($user === null || ! Hash::check((string) ($credentials['password'] ?? ''), (string) ($user['password'] ?? ''))) {
            abort(400, '密碼有誤');
        }

        $token = Str::random(64);
        $this->authServiceRepository->updateUserToken((int) $user['id'], $token);

        $agent = (string) data_get($credentials, 'users.agent', '');
        $ip = (string) data_get($credentials, 'users.ip', '');
        $this->authServiceRepository->updateUserAgentIp((int) $user['id'], $agent, $ip);

        if ((string) ($credentials['type'] ?? '') === 'bind') {
            $this->bindWalletUserByJwt(
                (int) $user['id'],
                (string) ($credentials['jwt_token'] ?? ''),
                $agent,
                $ip
            );
        }

        return $this->buildLoginResponse($user, $token);
    }

    /**
     * 第三方登入。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function thirdPartyLogin(array $payload): array
    {
        $provider = (string) ($payload['provider'] ?? '');
        $token = (string) ($payload['token'] ?? '');
        $cacheKey = sprintf('auth.thirdParty.%s.%s', $provider, $token);
        $socialEntity = Cache::get($cacheKey);

        $userId = $this->resolveThirdPartyUserId($socialEntity);
        if ($userId <= 0) {
            throw new RuntimeException('登入失敗');
        }

        $user = $this->authServiceRepository->findUserById($userId);
        if ($user === null) {
            throw new RuntimeException('登入失敗');
        }

        $memberToken = Str::random(64);
        $this->authServiceRepository->updateUserToken($userId, $memberToken);

        $agent = (string) data_get($payload, 'users.agent', '');
        $ip = (string) data_get($payload, 'users.ip', '');
        $this->authServiceRepository->updateUserAgentIp($userId, $agent, $ip);

        return $this->buildLoginResponse($user, $memberToken);
    }

    /**
     * 綁定邀請中的帳本成員。
     */
    private function bindWalletUserByJwt(int $userId, string $jwtToken, string $agent, string $ip): void
    {
        $payload = $this->decodeJwtPayload($jwtToken);
        $walletUserEncryptedId = (string) data_get($payload, 'wallet_user.id', '');
        if ($walletUserEncryptedId === '') {
            throw new RuntimeException('系統錯誤查詢不到綁定資訊');
        }

        try {
            $walletUserId = (int) Crypt::decryptString($walletUserEncryptedId);
        } catch (\Throwable) {
            throw new RuntimeException('系統錯誤查詢不到綁定資訊');
        }

        $walletUser = $this->authServiceRepository->findWalletUserById($walletUserId);
        if ($walletUser === null) {
            throw new RuntimeException('系統錯誤查詢不到綁定資訊');
        }

        $walletId = (int) ($walletUser['wallet_id'] ?? 0);
        $boundUserId = (int) ($walletUser['user_id'] ?? 0);
        if (
            $walletId <= 0
            || $boundUserId > 0
            || $this->authServiceRepository->walletUserExistsByWalletAndUser($walletId, $userId)
        ) {
            throw new RuntimeException('已被綁定或是有重複的帳本使用者');
        }

        $bound = $this->authServiceRepository->bindWalletUser($walletUserId, $userId, $agent, $ip);
        if (! $bound) {
            throw new RuntimeException('已被綁定或是有重複的帳本使用者');
        }
    }

    /**
     * 解析 JWT payload。
     *
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

    /**
     * 從第三方登入快取資料中解析使用者 ID。
     */
    private function resolveThirdPartyUserId(mixed $socialEntity): int
    {
        if (is_object($socialEntity) && method_exists($socialEntity, 'users')) {
            $user = $socialEntity->users()->first();

            return $user ? (int) ($user->id ?? 0) : 0;
        }

        if (is_array($socialEntity)) {
            return (int) data_get($socialEntity, 'user.id', data_get($socialEntity, 'user_id', 0));
        }

        return 0;
    }

    /**
     * 建立登入回應資料。
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function buildLoginResponse(array $user, string $memberToken): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $wallet = $this->authServiceRepository->findLatestOwnedWalletByUserId($userId);

        return [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? ''),
            'member_token' => $memberToken,
            'jwt' => $this->jwtTokenService->makeUserJwt($user),
            'wallet' => [
                'id' => $wallet ? (int) ($wallet['id'] ?? 0) : null,
                'code' => $wallet ? (string) ($wallet['code'] ?? '') : null,
            ],
            'walletUsers' => $this->authServiceRepository->listWalletUsersByUserId($userId),
            'devices' => $this->authServiceRepository->listActiveDevicesByUserId($userId),
            'notifies' => $this->authServiceRepository->listNotifiesByUserId($userId),
        ];
    }

    /**
     * 註冊。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function register(array $payload): array
    {
        $account = (string) ($payload['account'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $name = (string) ($payload['name'] ?? '');

        $exists = $this->authServiceRepository->accountExists($account);
        if ($exists) {
            abort(400, '帳號已存在');
        }

        $token = Str::random(64);
        $id = $this->authServiceRepository->createUser([
            'name' => $name,
            'account' => $account,
            'password' => Hash::make($password),
            'token' => $token,
        ]);

        $jwt = $this->jwtTokenService->makeUserJwt([
            'id' => $id,
            'account' => $account,
            'name' => $name,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'member_token' => $token,
            'jwt' => $jwt,
            'wallet' => ['id' => null, 'code' => null],
            'walletUsers' => [],
            'devices' => [],
            'notifies' => [],
        ];
    }

    /**
     * auth/cache API 回傳資料。
     *
     * @return array<int, mixed>
     */
    public function cache(): array
    {
        return [];
    }

    /**
     * 登出。
     *
     * @return array<string, mixed>
     */
    public function logout(Request $request): array
    {
        $userId = (int) data_get($request->input('user', []), 'id', 0);
        if ($userId > 0) {
            $this->authServiceRepository->updateUserToken($userId, Str::random(64));
        }

        return ['token' => ''];
    }
}
