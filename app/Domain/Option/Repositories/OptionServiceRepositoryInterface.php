<?php

declare(strict_types=1);

namespace App\Domain\Option\Repositories;

interface OptionServiceRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(): array;
}
