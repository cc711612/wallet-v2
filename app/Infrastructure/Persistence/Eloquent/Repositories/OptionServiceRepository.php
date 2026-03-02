<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Option\Entities\CategoryEntity;
use App\Domain\Option\Repositories\OptionServiceRepositoryInterface;

class OptionServiceRepository implements OptionServiceRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(): array
    {
        return CategoryEntity::query()
            ->orderBy('id')
            ->get(['id', 'parent_id', 'wallet_id', 'name', 'icon'])
            ->map(static fn (CategoryEntity $item): array => [
                'id' => (int) $item->id,
                'parent_id' => $item->parent_id ? (int) $item->parent_id : null,
                'wallet_id' => $item->wallet_id ? (int) $item->wallet_id : null,
                'name' => (string) $item->name,
                'icon' => $item->icon,
            ])
            ->all();
    }
}
