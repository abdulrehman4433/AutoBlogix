<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AiContentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the AI Content composer (create + generate in one step).
 */
class StoreAiContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize the comma-separated keyword list into an array.
     */
    public function prepareForValidation(): void
    {
        $value = $this->input('keywords');

        if (! is_string($value)) {
            return;
        }

        $entries = array_filter(array_map(
            static fn (string $entry): string => trim($entry),
            explode(',', $value),
        ));

        $this->merge(['keywords' => array_values($entries)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'website_id' => [
                'required',
                'integer',
                Rule::exists('websites', 'id')->where('user_id', $this->user()?->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:200'],
            'keywords' => ['nullable', 'array', 'max:10'],
            'keywords.*' => ['string', 'max:50'],
            'tone' => ['required', 'string', Rule::in(array_keys(AiContentService::TONES))],
            'length' => ['required', 'string', Rule::in(array_keys(AiContentService::LENGTHS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_id.exists' => 'Choose one of your own websites.',
        ];
    }
}
