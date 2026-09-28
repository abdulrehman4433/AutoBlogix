<?php

declare(strict_types=1);

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Generated = 'generated';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label for UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generating => 'Generating',
            self::Generated => 'Generated',
            self::Scheduled => 'Scheduled',
            self::Publishing => 'Publishing',
            self::Published => 'Published',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Badge variant name used by components/badge.blade.php.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Published => 'green',
            self::Scheduled => 'blue',
            self::Generating, self::Publishing => 'indigo',
            self::Generated => 'amber',
            self::Failed => 'red',
            self::Draft, self::Cancelled => 'gray',
        };
    }

    /**
     * Statuses that are allowed to move into "publishing" (idempotency rule).
     *
     * @return list<self>
     */
    public static function publishable(): array
    {
        return [self::Scheduled, self::Failed];
    }
}
