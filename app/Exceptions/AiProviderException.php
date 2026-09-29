<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * AI provider failure with a user-facing message (`friendly`) and a
 * log-only technical detail — the friendly text is what reaches the UI,
 * the technical text goes to ai_logs / the Laravel log.
 */
class AiProviderException extends Exception
{
    public function __construct(
        public readonly string $friendly,
        public readonly ?string $technical = null,
    ) {
        parent::__construct($friendly);
    }
}
