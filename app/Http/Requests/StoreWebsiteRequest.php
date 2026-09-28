<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidWebsiteUrl;
use App\Support\UrlNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Canonicalize the URL before validation so uniqueness checks and the
     * stored value always agree.
     */
    public function prepareForValidation(): void
    {
        $url = $this->input('url');

        if (! is_string($url)) {
            return;
        }

        try {
            $this->merge(['url' => UrlNormalizer::normalize($url)]);
        } catch (\InvalidArgumentException) {
            // Leave as entered; ValidWebsiteUrl reports the friendly message.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => [
                'required',
                'string',
                'max:255',
                new ValidWebsiteUrl,
                Rule::unique('websites', 'url')->where('user_id', $this->user()?->id),
            ],
            'timezone' => ['required', 'string', 'max:64', $this->validTimezone()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.unique' => 'You already added this website.',
        ];
    }

    /**
     * Validates against the real timezone database instead of a static list.
     *
     * @return callable(string, mixed, Closure): void
     */
    private function validTimezone(): callable
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                new \DateTimeZone(is_string($value) ? $value : '');
            } catch (\Exception) {
                $fail('The selected timezone is not valid.');
            }
        };
    }
}
