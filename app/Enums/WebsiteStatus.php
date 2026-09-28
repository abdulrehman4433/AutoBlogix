<?php

declare(strict_types=1);

namespace App\Enums;

enum WebsiteStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';

    /**
     * Human-readable label for UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending connection',
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
            self::Error => 'Error',
        };
    }

    /**
     * Badge variant name used by components/badge.blade.php.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Connected => 'green',
            self::Pending => 'amber',
            self::Disconnected => 'gray',
            self::Error => 'red',
        };
    }
}
