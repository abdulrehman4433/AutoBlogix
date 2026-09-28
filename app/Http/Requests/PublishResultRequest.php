<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * POST /api/v1/wordpress/publish-result — plugin reports a publish outcome.
 * The conditional field requirements are derived from the boolean "success"
 * flag; JSON booleans are normalized with an explicit cast rather than
 * required_if comparisons (which compare against strings).
 */
class PublishResultRequest extends WordPressApiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $success = (bool) $this->input('success');

        return [
            'idempotency_key' => ['required', 'uuid', 'max:64'],
            'success' => ['required', 'boolean'],
            'wordpress_post_id' => [$success ? 'required' : 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'url' => [$success ? 'required' : 'nullable', 'url', 'max:500'],
            'message' => [$success ? 'nullable' : 'required', 'string', 'max:500'],
        ];
    }
}
