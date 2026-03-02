<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

interface UserTokenRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByAccount(string $account): ?array;
}
