<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Support\JwtTokenService;
use Illuminate\Http\Request;
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

        $wallet = $this->authServiceRepository->findLatestOwnedWalletByUserId((int) $user['id']);
        $walletUsers = $this->authServiceRepository->listWalletUsersByUserId((int) $user['id']);
        $devices = $this->authServiceRepository->listActiveDevicesByUserId((int) $user['id']);
        $notifies = $this->authServiceRepository->listNotifiesByUserId((int) $user['id']);

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'member_token' => $token,
            'jwt' => $this->jwtTokenService->makeUserJwt($user),
            'wallet' => [
                'id' => $wallet ? (int) ($wallet['id'] ?? 0) : null,
                'code' => $wallet ? (string) ($wallet['code'] ?? '') : null,
            ],
            'walletUsers' => $walletUsers,
            'devices' => $devices,
            'notifies' => $notifies,
        ];
    }

    /**
     * @param  int  $userId
     * @param  string  $jwtToken
     * @param  string  $agent
     * @param  string  $ip
     * @return void
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
     * @param  string  $jwt
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
     * @return array<int, mixed>
     */
    public function cache(): array
    {
        return [];
    }

    /**
     * @param  Request  $request
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
