<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base request for WordPress API endpoints. Authorization is handled by the
 * HMAC signature middleware, not by the session; validation failures render
 * the documented 422 envelope with the stable "validation_failed" code.
 */
abstract class WordPressApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'error' => 'validation_failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
