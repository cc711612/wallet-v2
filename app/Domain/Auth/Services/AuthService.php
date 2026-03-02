<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Support\JwtTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $walletUser = $this->authServiceRepository->findLatestWalletUserByUserId((int) $user['id']);

        $walletId = $walletUser ? (int) ($walletUser['wallet_id'] ?? 0) : 0;
        $wallet = $walletId > 0 ? $this->authServiceRepository->findWalletById($walletId) : null;

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'member_token' => $token,
            'jwt' => $this->jwtTokenService->makeUserJwt($user),
            'wallet' => [
                'id' => $wallet ? (int) ($wallet['id'] ?? 0) : null,
                'code' => $wallet ? (string) ($wallet['code'] ?? '') : null,
            ],
            'walletUsers' => $walletUser ? [$walletUser] : [],
            'devices' => [],
            'notifies' => [],
        ];
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
