<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use App\Domain\Wallet\Services\WalletUserRegisterJobService;
use Tests\TestCase;

class WalletUserRegisterJobServiceTest extends TestCase
{
    public function test_it_syncs_all_select_all_details_in_a_single_batch_call(): void
    {
        $repository = new RecordingWalletJobRepository(
            walletUserIds: [10, 11, 12],
            selectAllDetailIds: [100, 101, 102],
        );

        $service = new WalletUserRegisterJobService($repository);
        $service->syncSelectedDetailsForWalletUsers(1);

        $this->assertSame(1, $repository->batchCallCount);
        $this->assertSame(0, $repository->perDetailCallCount);
        $this->assertSame([100, 101, 102], $repository->lastBatchDetailIds);
        $this->assertSame([10, 11, 12], $repository->lastBatchWalletUserIds);
    }

    public function test_it_does_nothing_when_wallet_has_no_users(): void
    {
        $repository = new RecordingWalletJobRepository(walletUserIds: [], selectAllDetailIds: [100]);

        $service = new WalletUserRegisterJobService($repository);
        $service->syncSelectedDetailsForWalletUsers(1);

        $this->assertSame(0, $repository->batchCallCount);
    }

    public function test_it_does_nothing_when_there_are_no_select_all_details(): void
    {
        $repository = new RecordingWalletJobRepository(walletUserIds: [10, 11], selectAllDetailIds: []);

        $service = new WalletUserRegisterJobService($repository);
        $service->syncSelectedDetailsForWalletUsers(1);

        $this->assertSame(0, $repository->batchCallCount);
    }

    public function test_it_does_nothing_for_invalid_wallet_id(): void
    {
        $repository = new RecordingWalletJobRepository(walletUserIds: [10], selectAllDetailIds: [100]);

        $service = new WalletUserRegisterJobService($repository);
        $service->syncSelectedDetailsForWalletUsers(0);

        $this->assertSame(0, $repository->batchCallCount);
    }
}

class RecordingWalletJobRepository implements WalletJobRepositoryInterface
{
    public int $batchCallCount = 0;

    public int $perDetailCallCount = 0;

    /** @var array<int, int> */
    public array $lastBatchDetailIds = [];

    /** @var array<int, int> */
    public array $lastBatchWalletUserIds = [];

    /**
     * @param  array<int, int>  $walletUserIds
     * @param  array<int, int>  $selectAllDetailIds
     */
    public function __construct(
        private array $walletUserIds,
        private array $selectAllDetailIds,
    ) {}

    public function findUserById(int $userId): ?array
    {
        return null;
    }

    public function listWalletUserIds(int $walletId): array
    {
        return $this->walletUserIds;
    }

    public function listSelectAllDetailIds(int $walletId): array
    {
        return $this->selectAllDetailIds;
    }

    public function syncDetailUsersWithoutDetaching(int $detailId, array $walletUserIds): void
    {
        $this->perDetailCallCount++;
    }

    public function syncDetailUsersWithoutDetachingBatch(array $detailIds, array $walletUserIds): void
    {
        $this->batchCallCount++;
        $this->lastBatchDetailIds = $detailIds;
        $this->lastBatchWalletUserIds = $walletUserIds;
    }

    public function findWalletUserByWalletAndUser(int $walletId, int $userId): ?array
    {
        return null;
    }

    public function findAdminWalletUserByWalletId(int $walletId): ?array
    {
        return null;
    }

    public function createWalletDetail(array $attributes): int
    {
        return 1;
    }

    public function syncDetailUsers(int $detailId, array $walletUserIds): void {}
}
