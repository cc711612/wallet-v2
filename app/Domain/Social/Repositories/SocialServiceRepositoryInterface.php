<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

interface SocialServiceRepositoryInterface
{
    /**
     * 依社群類型與值查詢。
     *
     * @param  int  $socialType
     * @param  string  $socialTypeValue
     * @return array<string, mixed>|null
     */
    public function findByTypeAndValue(int $socialType, string $socialTypeValue): ?array;

    /**
     * 建立或更新社群資料。
     *
     * @param  int  $socialType
     * @param  string  $socialTypeValue
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateOrCreateByTypeAndValue(int $socialType, string $socialTypeValue, array $attributes): array;

    /**
     * 綁定社群到使用者。
     *
     * @param  int  $socialId
     * @param  int  $userId
     * @return void
     */
    public function bindSocialToUser(int $socialId, int $userId): void;

    /**
     * 解除使用者指定社群綁定。
     *
     * @param  int  $socialType
     * @param  int  $userId
     * @return void
     */
    public function unbindSocialByTypeAndUser(int $socialType, int $userId): void;

    /**
     * 檢查社群資料是否存在。
     *
     * @param  int  $socialType
     * @param  string  $socialTypeValue
     * @return bool
     */
    public function existsByTypeAndValue(int $socialType, string $socialTypeValue): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSocialsByUserId(int $userId): array;
}
