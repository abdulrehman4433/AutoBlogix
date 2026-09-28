<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Signals a rejected WordPress API request. Carries a stable machine-readable
 * error code plus the HTTP status the JSON envelope should use.
 */
class WordPressAuthenticationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 401,
    ) {
        parent::__construct($message);
    }
}
