<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use Tests\TestCase;

class WalletDetailStoreContractTest extends TestCase
{
    public function test_it_matches_success_envelope_without_db_access(): void
    {
        $this->app->bind(WalletDetailRepositoryInterface::class, static fn (): WalletDetailRepositoryInterface => new ApiFakeWalletDetailRepository);

        $payload = [
            'wallet_user_id' => 10,
            'type' => 2,
            'symbol_operation_type_id' => 2,
            'title' => 'Dinner',
            'value' => 300,
            'select_all' => false,
            'users' => [10, 11],
            'splits' => [
                ['user_id' => 10, 'value' => 150],
                ['user_id' => 11, 'value' => 150],
            ],
        ];

        $response = $this->postJson('/api/wallet/1/detail', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', '')
            ->assertJsonPath('data.wallet_detail.id', 9001)
            ->assertJsonPath('data.wallet_detail.wallet_id', 1)
            ->assertJsonPath('data.wallet_detail.title', 'Dinner');
    }
}

class ApiFakeWalletDetailRepository implements WalletDetailRepositoryInterface
{
    public function getWalletBalance(int $walletId): float
    {
        return 9999.0;
    }

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $walletDetail->toPersistenceAttributes();

        return [
            'id' => 9001,
            'wallet_id' => $attributes['wallet_id'],
            'title' => $attributes['title'],
            'value' => $attributes['value'],
            'type' => $attributes['type'],
            'symbol_operation_type_id' => $attributes['symbol_operation_type_id'],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
