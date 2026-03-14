<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

interface SocialServiceRepositoryInterface
{
    /**
     * 依社群類型與值查詢。
     *
     * @return array<string, mixed>|null
     */
    public function findByTypeAndValue(int $socialType, string $socialTypeValue): ?array;

    /**
     * 建立或更新社群資料。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateOrCreateByTypeAndValue(int $socialType, string $socialTypeValue, array $attributes): array;

    /**
     * 綁定社群到使用者。
     */
    public function bindSocialToUser(int $socialId, int $userId): void;

    /**
     * 解除使用者指定社群綁定。
     */
    public function unbindSocialByTypeAndUser(int $socialType, int $userId): void;

    /**
     * 檢查社群資料是否存在。
     */
    public function existsByTypeAndValue(int $socialType, string $socialTypeValue): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSocialsByUserId(int $userId): array;
}
