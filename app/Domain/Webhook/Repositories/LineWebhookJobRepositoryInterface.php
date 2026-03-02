<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Repositories;

interface LineWebhookJobRepositoryInterface
{
    public function startLoading(string $lineUserId): void;

    public function replyText(string $replyToken, string $message): void;

    public function pushText(string $lineUserId, string $message): void;

    public function findUserIdByLineUserId(string $lineUserId): ?int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWalletsByUserId(int $userId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findWalletByCodeForUser(int $userId, string $code): ?array;

    public function updateSocialWalletIdByLineUserId(string $lineUserId, int $walletId): void;

    public function findSocialWalletIdByLineUserId(string $lineUserId): ?int;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function listLineUserIdsByUserIds(array $userIds): array;

    public function firstCategoryId(): ?int;
}
