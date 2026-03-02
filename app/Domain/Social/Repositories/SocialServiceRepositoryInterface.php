<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

interface SocialServiceRepositoryInterface
{
    public function existsByTypeAndValue(string $socialType, string $socialTypeValue): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSocialsByUserId(int $userId): array;
}
