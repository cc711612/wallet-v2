<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletUserServiceRepositoryInterface;
use RuntimeException;

class WalletUserService
{
    /**
     * @return void
     */
    public function __construct(private WalletUserServiceRepositoryInterface $walletUserRepository) {}

    /**
     * @return array<string, mixed>
     */
    public function index(string $code): array
    {
        $wallet = $this->walletUserRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('帳本驗證碼錯誤');
        }

        $users = $this->walletUserRepository->listWalletUsers((int) $wallet['id']);

        return [
            'wallet' => [
                'users' => $users,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(int $walletUserId, array $payload): array
    {
        $updatePayload = ['updated_at' => now()];

        if (array_key_exists('name', $payload)) {
            $updatePayload['name'] = (string) $payload['name'];
        }

        if (array_key_exists('notify_enable', $payload)) {
            $updatePayload['notify_enable'] = (int) ((bool) $payload['notify_enable']);
        }

        $this->walletUserRepository->updateWalletUser($walletUserId, $updatePayload);

        return [
            'wallet_user_id' => $walletUserId,
            'name' => (string) ($payload['name'] ?? 'wallet-member'),
            'notify_enable' => (bool) ($payload['notify_enable'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(int $walletId, int $walletUserId): array
    {
        $this->walletUserRepository->deleteWalletUser($walletId, $walletUserId);

        return ['wallet_id' => $walletId, 'wallet_user_id' => $walletUserId, 'deleted' => true];
    }
}
