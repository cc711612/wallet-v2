<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Entities\UserEntity;
use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;

class UserTokenRepository implements UserTokenRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        /** @var UserEntity|null $record */
        $record = UserEntity::query()
            ->where('token', $token)
            ->first(['id', 'name', 'token']);

        if (! $record instanceof UserEntity) {
            return null;
        }

        /** @var array<string, mixed> $user */
        $user = $record->toArray();

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $record = UserEntity::query()->where('id', $id)->first(['id', 'name', 'account', 'token']);

        return $record?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByAccount(string $account): ?array
    {
        $record = UserEntity::query()->where('account', $account)->first(['id', 'name', 'account', 'token']);

        return $record?->toArray();
    }
}
