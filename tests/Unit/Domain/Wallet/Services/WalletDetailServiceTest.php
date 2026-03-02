<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Services;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Enums\SymbolOperationType;
use App\Domain\Wallet\Enums\WalletDetailType;
use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use App\Domain\Wallet\Services\WalletDetailService;
use Tests\TestCase;

class WalletDetailServiceTest extends TestCase
{
    public function test_it_throws_when_public_expense_would_be_negative(): void
    {
        $repository = new InMemoryWalletDetailRepository(100.0, true);
        $service = new WalletDetailService($repository, new InMemoryWalletDetailQueryRepository);

        $this->expectException(WalletDetailBusinessException::class);
        $this->expectExceptionMessage('公費結算金額不得為負數');

        $service->create(WalletDetail::fromPayload([
            'wallet' => 1,
            'wallet_user_id' => 10,
            'type' => WalletDetailType::PUBLIC_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => 'Lunch',
            'value' => 999,
            'select_all' => true,
        ]));
    }

    public function test_it_throws_when_wallet_users_are_invalid(): void
    {
        $repository = new InMemoryWalletDetailRepository(1000.0, false);
        $service = new WalletDetailService($repository, new InMemoryWalletDetailQueryRepository);

        $this->expectException(WalletDetailBusinessException::class);
        $this->expectExceptionMessage('分攤成員有誤');

        $service->create(WalletDetail::fromPayload([
            'wallet' => 1,
            'wallet_user_id' => 10,
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => 'Taxi',
            'value' => 200,
            'select_all' => false,
            'users' => [10, 11],
        ]));
    }

    public function test_it_creates_wallet_detail_when_rules_pass(): void
    {
        $repository = new InMemoryWalletDetailRepository(1000.0, true);
        $service = new WalletDetailService($repository, new InMemoryWalletDetailQueryRepository);

        $result = $service->create(WalletDetail::fromPayload([
            'wallet' => 1,
            'wallet_user_id' => 10,
            'type' => WalletDetailType::GENERAL_EXPENSE->value,
            'symbol_operation_type_id' => SymbolOperationType::DECREMENT->value,
            'title' => 'Dinner',
            'value' => 300,
            'select_all' => false,
            'users' => [10, 11],
        ]));

        $this->assertSame(1, $result['id']);
        $this->assertSame('Dinner', $result['title']);
    }
}

class InMemoryWalletDetailRepository implements WalletDetailRepositoryInterface
{
    private float $walletBalance;

    private bool $walletUsersValid;

    /**
     * @return void
     */
    public function __construct(float $walletBalance, bool $walletUsersValid)
    {
        $this->walletBalance = $walletBalance;
        $this->walletUsersValid = $walletUsersValid;
    }

    public function getWalletBalance(int $walletId): float
    {
        return $this->walletBalance;
    }

    /**
     * @param  array<int, int>  $walletUserIds
     */
    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool
    {
        return $this->walletUsersValid;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(WalletDetail $walletDetail): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $walletDetail->toPersistenceAttributes();

        return [
            'id' => 1,
            'wallet_id' => $attributes['wallet_id'],
            'title' => $attributes['title'],
            'value' => $attributes['value'],
            'type' => $attributes['type'],
            'symbol_operation_type_id' => $attributes['symbol_operation_type_id'],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}

class InMemoryWalletDetailQueryRepository implements WalletDetailQueryRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetails(int $walletId, bool $isPersonal): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletUsers(int $walletId): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetail(int $walletId, int $detailId): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWallet(int $walletId): ?array
    {
        return [
            'id' => $walletId,
            'code' => 'WALLET001',
            'title' => 'test-wallet',
            'status' => 1,
            'mode' => 'multi',
            'unit' => 'TWD',
            'properties' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDetail(int $walletId, int $detailId, array $attributes): void {}

    public function deleteDetail(int $walletId, int $detailId): void {}

    /**
     * @param  array<int, int>  $detailIds
     */
    public function checkout(int $walletId, array $detailIds): void {}

    public function uncheckout(int $walletId, string $checkoutAt): void {}
}
