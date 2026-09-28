<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionLogStatus: string
{
    case Success = 'success';
    case Failed = 'failed';

    /**
     * Human-readable label for UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }

    /**
     * Badge variant name used by components/badge.blade.php.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Success => 'green',
            self::Failed => 'red',
        };
    }
}
