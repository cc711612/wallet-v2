<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletAuthServiceRepositoryInterface;
use App\Jobs\WalletUserRegister;
use App\Support\JwtTokenService;
use Illuminate\Support\Str;
use RuntimeException;

class WalletAuthService
{
    /**
     * @return void
     */
    public function __construct(
        private WalletAuthServiceRepositoryInterface $walletAuthRepository,
        private JwtTokenService $jwtTokenService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function login(array $payload): array
    {
        $code = (string) ($payload['code'] ?? '');
        $name = (string) ($payload['name'] ?? '');

        $wallet = $this->walletAuthRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('此帳簿不存在', 400);
        }

        $walletUser = $this->walletAuthRepository->findWalletUserByName((int) $wallet['id'], $name);

        if ($walletUser === null) {
            throw new RuntimeException('此用戶不存在', 400);
        }

        if ((int) ($walletUser['is_admin'] ?? 0) === 1) {
            throw new RuntimeException('管理者不得使用此方式登入', 401);
        }

        $token = Str::random(64);
        $walletUser = $this->walletAuthRepository->updateWalletUserToken((int) $walletUser['id'], $token);

        return [
            'id' => (int) $walletUser['id'],
            'name' => (string) $walletUser['name'],
            'wallet_id' => (int) $wallet['id'],
            'member_token' => $token,
            'jwt' => $this->jwtTokenService->makeWalletUserJwt($walletUser),
            'wallet' => ['id' => (int) $wallet['id'], 'code' => (string) $wallet['code']],
            'devices' => $this->walletAuthRepository->listDevicesByWalletUserId((int) $walletUser['id']),
            'notifies' => $this->walletAuthRepository->listNotifiesByWalletUserId((int) $walletUser['id']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function token(array $payload): array
    {
        $code = (string) ($payload['code'] ?? '');
        $memberToken = (string) ($payload['member_token'] ?? '');

        $wallet = $this->walletAuthRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('此帳簿不存在', 400);
        }

        $walletUser = $this->walletAuthRepository->findWalletUserByToken((int) $wallet['id'], $memberToken);

        if ($walletUser === null) {
            throw new RuntimeException('此token不存在', 400);
        }

        if ((int) ($walletUser['is_admin'] ?? 0) === 1) {
            throw new RuntimeException('管理者不得使用此方式登入', 401);
        }

        return [
            'id' => (int) $walletUser['id'],
            'name' => (string) $walletUser['name'],
            'wallet_id' => (int) $walletUser['wallet_id'],
            'member_token' => (string) $walletUser['token'],
            'wallet' => ['id' => (int) $wallet['id'], 'code' => (string) $wallet['code']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function register(array $payload): array
    {
        $code = (string) ($payload['code'] ?? '');
        $name = (string) ($payload['name'] ?? '');

        $wallet = $this->walletAuthRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('此帳簿不存在', 400);
        }

        $exists = $this->walletAuthRepository->walletUserNameExists((int) $wallet['id'], $name);

        if ($exists) {
            throw new RuntimeException('名稱已存在', 400);
        }

        $token = Str::random(64);
        $id = $this->walletAuthRepository->createWalletUser([
            'wallet_id' => (int) $wallet['id'],
            'name' => $name,
            'token' => $token,
            'is_admin' => 0,
            'notify_enable' => 0,
        ]);

        WalletUserRegister::dispatch([
            'wallet' => [
                'id' => (int) $wallet['id'],
            ],
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'member_token' => $token,
            'wallet' => ['id' => (int) $wallet['id'], 'code' => (string) $wallet['code']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerBatch(array $payload): array
    {
        $code = (string) ($payload['code'] ?? '');
        /** @var array<int, string> $names */
        $names = array_values(array_unique((array) ($payload['name'] ?? [])));

        $wallet = $this->walletAuthRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('此帳簿不存在', 400);
        }

        $rows = array_map(
            static fn (string $name): array => [
                'wallet_id' => (int) $wallet['id'],
                'name' => $name,
                'token' => Str::random(64),
                'is_admin' => 0,
                'notify_enable' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $names
        );

        $this->walletAuthRepository->batchCreateWalletUsers($rows);

        WalletUserRegister::dispatch([
            'wallet' => [
                'id' => (int) $wallet['id'],
            ],
        ]);

        return ['count' => count($rows)];
    }
}
