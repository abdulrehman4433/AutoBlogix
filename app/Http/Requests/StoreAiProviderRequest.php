<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\UrlNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for saving an AI provider configuration. The key is write-only
 * (encrypted at rest, masked hint afterwards) and a custom base URL must
 * pass the same SSRF rules as website URLs.
 */
class StoreAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Trim free-text fields before validation.
     */
    public function prepareForValidation(): void
    {
        foreach (['api_key', 'model', 'base_url'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
            'model' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'base_url' => ['nullable', 'string', 'max:255', $this->allowedBaseUrl()],
        ];
    }

    /**
     * Shared SSRF policy: only public http(s) addresses outside local env.
     *
     * @return callable(string, mixed, Closure): void
     */
    private function allowedBaseUrl(): callable
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (! UrlNormalizer::isAllowedUrl($value)) {
                $fail('This URL is not allowed. Use a public http:// or https:// address.');
            }
        };
    }
}
