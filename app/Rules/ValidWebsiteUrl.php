<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\UrlNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Validates and (via FormRequest::prepareForValidation) normalizes the
 * website URL, enforcing the SSRF host rules outside local environments.
 */
class ValidWebsiteUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The website URL is required.');

            return;
        }

        try {
            $normalized = UrlNormalizer::normalize($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());

            return;
        }

        if (! UrlNormalizer::isAllowedUrl($normalized)) {
            $fail('The website URL must be a public address. Private and local addresses are not allowed.');
        }
    }
}
