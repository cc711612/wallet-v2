<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Services;

use App\Domain\Wallet\Entities\WalletDetail;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use App\Domain\Wallet\Services\CreateWalletDetailJobService;
use App\Domain\Wallet\Services\WalletDetailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateWalletDetailJobServiceTest extends TestCase
{
    public function test_it_creates_detail_directly_through_domain_service_without_http(): void
    {
        Http::fake();

        $walletJobRepository = new FakeWalletJobRepository(
            user: ['id' => 42, 'name' => 'Roy'],
            walletUser: ['id' => 7, 'wallet_id' => 1, 'is_admin' => 0],
        );
        $walletDetailRepository = new RecordingCreateJobWalletDetailRepository;
        $service = new CreateWalletDetailJobService(
            $walletJobRepository,
            new WalletDetailService($walletDetailRepository, new NullWalletDetailQueryRepository)
        );

        $service->createGeneralExpenseDetail(42, 1, [
            'title' => 'Taxi',
            'amount' => 300,
            'unit' => 'TWD',
            'date' => '2026-07-01',
            'categoryId' => 9,
        ]);

        Http::assertNothingSent();

        $this->assertNotNull($walletDetailRepository->created);
        $attributes = $walletDetailRepository->created->toPersistenceAttributes();

        $this->assertSame(1, $attributes['wallet_id']);
        $this->assertSame(2, $attributes['type']);
        $this->assertSame(2, $attributes['symbol_operation_type_id']);
        $this->assertSame('Taxi', $attributes['title']);
        $this->assertSame(300.0, $attributes['value']);
        $this->assertSame('TWD', $attributes['unit']);
        $this->assertTrue($attributes['select_all']);
        $this->assertFalse($attributes['is_personal']);
        $this->assertSame(7, $attributes['payment_wallet_user_id']);
        $this->assertSame('2026-07-01', $attributes['date']);
        $this->assertSame(9, $attributes['category_id']);
        $this->assertSame(7, $attributes['created_by']);
        $this->assertSame(7, $attributes['updated_by']);
    }

    public function test_it_falls_back_to_admin_wallet_user_when_creator_not_a_member(): void
    {
        Http::fake();

        $walletJobRepository = new FakeWalletJobRepository(
            user: ['id' => 42, 'name' => 'Roy'],
            walletUser: null,
            adminWalletUser: ['id' => 99, 'wallet_id' => 1, 'is_admin' => 1],
        );
        $walletDetailRepository = new RecordingCreateJobWalletDetailRepository;
        $service = new CreateWalletDetailJobService(
            $walletJobRepository,
            new WalletDetailService($walletDetailRepository, new NullWalletDetailQueryRepository)
        );

        $service->createGeneralExpenseDetail(42, 1, ['title' => 'Snack', 'amount' => 50]);

        Http::assertNothingSent();
        $this->assertNotNull($walletDetailRepository->created);
        $attributes = $walletDetailRepository->created->toPersistenceAttributes();
        $this->assertSame(99, $attributes['payment_wallet_user_id']);
    }

    public function test_it_does_nothing_when_user_not_found(): void
    {
        Http::fake();

        $walletJobRepository = new FakeWalletJobRepository(user: null, walletUser: null);
        $walletDetailRepository = new RecordingCreateJobWalletDetailRepository;
        $service = new CreateWalletDetailJobService(
            $walletJobRepository,
            new WalletDetailService($walletDetailRepository, new NullWalletDetailQueryRepository)
        );

        $service->createGeneralExpenseDetail(42, 1, ['title' => 'X', 'amount' => 1]);

        Http::assertNothingSent();
        $this->assertNull($walletDetailRepository->created);
    }

    public function test_it_does_nothing_when_no_wallet_user_can_be_resolved(): void
    {
        Http::fake();

        $walletJobRepository = new FakeWalletJobRepository(
            user: ['id' => 42, 'name' => 'Roy'],
            walletUser: null,
            adminWalletUser: null,
        );
        $walletDetailRepository = new RecordingCreateJobWalletDetailRepository;
        $service = new CreateWalletDetailJobService(
            $walletJobRepository,
            new WalletDetailService($walletDetailRepository, new NullWalletDetailQueryRepository)
        );

        $service->createGeneralExpenseDetail(42, 1, ['title' => 'X', 'amount' => 1]);

        Http::assertNothingSent();
        $this->assertNull($walletDetailRepository->created);
    }
}

class FakeWalletJobRepository implements WalletJobRepositoryInterface
{
    /**
     * @param  array<string, mixed>|null  $user
     * @param  array<string, mixed>|null  $walletUser
     * @param  array<string, mixed>|null  $adminWalletUser
     */
    public function __construct(
        private ?array $user,
        private ?array $walletUser,
        private ?array $adminWalletUser = null,
    ) {}

    public function findUserById(int $userId): ?array
    {
        return $this->user;
    }

    public function listWalletUserIds(int $walletId): array
    {
        return [];
    }

    public function listSelectAllDetailIds(int $walletId): array
    {
        return [];
    }

    public function syncDetailUsersWithoutDetaching(int $detailId, array $walletUserIds): void {}

    public function syncDetailUsersWithoutDetachingBatch(array $detailIds, array $walletUserIds): void {}

    public function findWalletUserByWalletAndUser(int $walletId, int $userId): ?array
    {
        return $this->walletUser;
    }

    public function findAdminWalletUserByWalletId(int $walletId): ?array
    {
        return $this->adminWalletUser;
    }

    public function createWalletDetail(array $attributes): int
    {
        return 1;
    }

    public function syncDetailUsers(int $detailId, array $walletUserIds): void {}
}

class RecordingCreateJobWalletDetailRepository implements WalletDetailRepositoryInterface
{
    public ?WalletDetail $created = null;

    public function getWalletBalance(int $walletId): float
    {
        return 0.0;
    }

    public function walletUsersExistInWallet(int $walletId, array $walletUserIds): bool
    {
        return true;
    }

    public function create(WalletDetail $walletDetail): array
    {
        $this->created = $walletDetail;

        return ['id' => 1];
    }

    public function replaceSplits(int $detailId, array $splits, string $unit): void {}
}

class NullWalletDetailQueryRepository implements WalletDetailQueryRepositoryInterface
{
    public function listDetails(int $walletId, ?bool $isPersonal, ?int $walletUserId = null): array
    {
        return [];
    }

    public function listWalletUsers(int $walletId): array
    {
        return [];
    }

    public function findDetail(int $walletId, int $detailId): ?array
    {
        return null;
    }

    public function findWallet(int $walletId): ?array
    {
        return null;
    }

    public function updateDetail(int $walletId, int $detailId, array $attributes): void {}

    public function replaceDetailUsers(int $walletId, int $detailId, array $userIds): void {}

    public function deleteDetail(int $walletId, int $detailId): void {}

    public function checkout(int $walletId, array $detailIds, int $walletUserId): void {}

    public function uncheckout(int $walletId, string $checkoutAt, int $walletUserId): void {}
}
