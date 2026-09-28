<?php

declare(strict_types=1);

namespace App\Enums;

enum PostSource: string
{
    case Manual = 'manual';
    case Ai = 'ai';

    /**
     * Human-readable label for UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Ai => 'AI',
        };
    }
}
