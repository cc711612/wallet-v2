<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Social\Enums\SocialTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Repositories\LineWebhookJobRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineWebhookJobRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private LineWebhookJobRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LineWebhookJobRepository;
    }

    public function test_find_user_id_by_line_user_id_uses_correct_social_type_enum(): void
    {
        // 建立測試資料
        $social = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'test-line-user-id',
        ]);

        $user = \App\Domain\Auth\Entities\UserEntity::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account' => 'test@example.com',
            'password' => bcrypt('password'),
            'token' => 'test-token',
        ]);

        $social->users()->attach($user->id);

        $result = $this->repository->findUserIdByLineUserId('test-line-user-id');

        $this->assertSame($user->id, $result);
    }

    public function test_find_user_id_by_line_user_id_returns_null_when_not_found(): void
    {
        $result = $this->repository->findUserIdByLineUserId('non-existent-user');

        $this->assertNull($result);
    }

    public function test_update_social_wallet_id_by_line_user_id_uses_correct_social_type_enum(): void
    {
        // 建立測試資料
        $social = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'test-line-user-id',
            'wallet_id' => null,
        ]);

        $this->repository->updateSocialWalletIdByLineUserId('test-line-user-id', 456);

        $this->assertDatabaseHas('socials', [
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'test-line-user-id',
            'wallet_id' => 456,
        ]);
    }

    public function test_find_social_wallet_id_by_line_user_id_uses_correct_social_type_enum(): void
    {
        // 建立測試資料
        $social = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'test-line-user-id',
            'wallet_id' => 789,
        ]);

        $result = $this->repository->findSocialWalletIdByLineUserId('test-line-user-id');

        $this->assertSame(789, $result);
    }

    public function test_find_social_wallet_id_by_line_user_id_returns_null_when_no_wallet(): void
    {
        $social = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'test-line-user-id',
            'wallet_id' => null,
        ]);

        $result = $this->repository->findSocialWalletIdByLineUserId('test-line-user-id');

        $this->assertNull($result);
    }

    public function test_list_line_user_ids_by_user_ids_uses_correct_social_type_enum(): void
    {
        // 建立測試資料
        $user1 = \App\Domain\Auth\Entities\UserEntity::create([
            'name' => 'Test User 1',
            'email' => 'test1@example.com',
            'account' => 'test1@example.com',
            'password' => bcrypt('password'),
            'token' => 'test-token-1',
        ]);

        $user2 = \App\Domain\Auth\Entities\UserEntity::create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'account' => 'test2@example.com',
            'password' => bcrypt('password'),
            'token' => 'test-token-2',
        ]);

        $social1 = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'line-user-1',
        ]);

        $social2 = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'line-user-2',
        ]);

        $social1->users()->attach($user1->id);
        $social2->users()->attach($user2->id);

        $result = $this->repository->listLineUserIdsByUserIds([$user1->id, $user2->id]);

        $this->assertCount(2, $result);
        $this->assertContains('line-user-1', $result);
        $this->assertContains('line-user-2', $result);
    }

    public function test_list_line_user_ids_by_user_ids_returns_empty_for_empty_input(): void
    {
        $result = $this->repository->listLineUserIdsByUserIds([]);

        $this->assertSame([], $result);
    }

    public function test_list_line_user_ids_by_user_ids_filters_only_line_social_type(): void
    {
        // 建立不同社交類型的測試資料
        $user1 = \App\Domain\Auth\Entities\UserEntity::create([
            'name' => 'Test User 1',
            'email' => 'test1@example.com',
            'account' => 'test1@example.com',
            'password' => bcrypt('password'),
            'token' => 'test-token-1',
        ]);

        $user2 = \App\Domain\Auth\Entities\UserEntity::create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'account' => 'test2@example.com',
            'password' => bcrypt('password'),
            'token' => 'test-token-2',
        ]);

        $lineSocial = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_LINE->value,
            'social_type_value' => 'line-user',
        ]);

        $emailSocial = \App\Domain\Social\Entities\SocialEntity::create([
            'social_type' => SocialTypeEnum::SOCIAL_TYPE_EMAIL->value,
            'social_type_value' => 'email@example.com',
        ]);

        $lineSocial->users()->attach($user1->id);
        $emailSocial->users()->attach($user2->id);

        $result = $this->repository->listLineUserIdsByUserIds([$user1->id, $user2->id]);

        $this->assertCount(1, $result);
        $this->assertContains('line-user', $result);
        $this->assertNotContains('email@example.com', $result);
    }
}
