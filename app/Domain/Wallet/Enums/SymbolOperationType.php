<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum SymbolOperationType: int
{
    case INCREMENT = 1;

    case DECREMENT = 2;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): int => $type->value, self::cases());
    }
}
