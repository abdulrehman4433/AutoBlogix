<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The global AI prompt template editor (Settings). Long free-text fields —
 * the placeholder syntax is documentation only and substituted at
 * generation time, so it is deliberately not validated here.
 */
class UpdatePromptTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user (global templates, single-admin MVP)
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'system_prompt' => ['required', 'string', 'max:12000'],
            'user_prompt' => ['required', 'string', 'max:12000'],
        ];
    }
}
