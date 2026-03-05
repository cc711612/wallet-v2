<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletServiceRepositoryInterface;
use Illuminate\Support\Str;
use RuntimeException;

class WalletService
{
    /**
     * @param  WalletServiceRepositoryInterface  $walletServiceRepository
     * @return void
     */
    public function __construct(private WalletServiceRepositoryInterface $walletServiceRepository) {}

    /**
     * 帳本列表。
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        return $this->walletServiceRepository->listWallets($filters);
    }

    /**
     * 建立帳本與擁有者。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        $code = strtoupper(Str::random(8));
        $userId = (int) data_get($payload, 'user.id', 0);

        $wallet = $this->walletServiceRepository->createWallet([
            'user_id' => $userId ?: null,
            'code' => $code,
            'title' => (string) ($payload['title'] ?? 'New Wallet'),
            'unit' => (string) ($payload['unit'] ?? 'TWD'),
            'status' => 1,
            'properties' => $payload['properties'] ?? [],
            'mode' => (string) ($payload['mode'] ?? 'multi'),
        ]);

        $this->walletServiceRepository->createWalletOwner([
            'wallet_id' => (int) ($wallet['id'] ?? 0),
            'user_id' => $userId ?: null,
            'name' => (string) ($payload['owner_name'] ?? 'owner'),
            'token' => Str::random(64),
            'is_admin' => 1,
            'notify_enable' => 1,
        ]);

        return [
            'wallet' => [
                'id' => (int) ($wallet['id'] ?? 0),
                'code' => $code,
                'title' => (string) ($payload['title'] ?? 'New Wallet'),
                'mode' => (string) ($payload['mode'] ?? 'multi'),
                'status' => true,
                'created_at' => now()->toDateTimeString(),
            ],
        ];
    }

    /**
     * 更新帳本資料。
     *
     * @param  int  $walletId
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(int $walletId, array $payload): array
    {
        $updatePayload = [
            'title' => (string) ($payload['title'] ?? ''),
            'mode' => (string) ($payload['mode'] ?? 'multi'),
            'updated_at' => now(),
        ];

        if (array_key_exists('status', $payload)) {
            $updatePayload['status'] = (int) ((bool) $payload['status']);
        }

        $this->walletServiceRepository->updateWallet($walletId, $updatePayload);

        return ['wallet_id' => $walletId, 'updated' => true, 'title' => (string) ($payload['title'] ?? '')];
    }

    /**
     * 刪除帳本。
     *
     * @param  int  $walletId
     * @return string
     */
    public function destroy(int $walletId): string
    {
        $updated = $this->walletServiceRepository->deleteWallet($walletId);

        return $updated > 0 ? '錢包已成功刪除' : '刪除失敗';
    }

    /**
     * 綁定訪客帳本成員到目前使用者。
     *
     * @param  array<string, mixed>  $payload
     * @return string
     */
    public function bind(array $payload): string
    {
        $code = (string) ($payload['code'] ?? '');
        $name = (string) ($payload['name'] ?? '');
        $userId = (int) data_get($payload, 'user.id', 0);

        $wallet = $this->walletServiceRepository->findWalletByCode($code);
        if ($wallet === null) {
            throw new RuntimeException('此帳簿不存在');
        }

        $walletId = (int) ($wallet['id'] ?? 0);
        $walletUser = $this->walletServiceRepository->findWalletUserByName($walletId, $name);
        if ($walletUser === null || $userId <= 0) {
            throw new RuntimeException('系統錯誤');
        }

        if (
            (int) ($walletUser['user_id'] ?? 0) > 0
            || $this->walletServiceRepository->walletUserExistsByWalletAndUser($walletId, $userId)
        ) {
            throw new RuntimeException('已被綁定或是有重複的帳本使用者');
        }

        $bound = $this->walletServiceRepository->bindWalletUser((int) ($walletUser['id'] ?? 0), $userId);
        if (! $bound) {
            throw new RuntimeException('已被綁定或是有重複的帳本使用者');
        }

        return '綁定成功';
    }

    /**
     * 計算帳本統計資料。
     *
     * @param  int  $walletId
     * @return array<string, mixed>
     */
    public function calculation(int $walletId): array
    {
        $totals = $this->walletServiceRepository->walletDetailTotals($walletId);
        $publicTotals = $this->walletServiceRepository->walletPublicDetailTotals($walletId);
        $walletUsers = $this->walletServiceRepository->listWalletUsersByWalletId($walletId);

        $users = array_map(static fn (array $walletUser): array => [
            'id' => (int) ($walletUser['id'] ?? 0),
            'name' => (string) ($walletUser['name'] ?? ''),
            'income' => 0,
            'expenses' => 0,
            'total' => 0,
            'wallet_details_id' => [],
            'payment_wallet_details_id' => [],
        ], $walletUsers);

        return [
            'wallet' => [
                'id' => $walletId,
                'total' => [
                    'public' => [
                        'income' => $publicTotals['income'] ?? 0.0,
                        'expenses' => $publicTotals['expenses'] ?? 0.0,
                    ],
                    'income' => $totals['income'] ?? 0.0,
                    'expenses' => $totals['expenses'] ?? 0.0,
                ],
                'users' => $users,
                'details' => [],
            ],
        ];
    }
}
