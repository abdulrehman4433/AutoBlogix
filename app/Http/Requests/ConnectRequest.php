<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * POST /api/v1/wordpress/connect — plugin handshake payload.
 */
class ConnectRequest extends WordPressApiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'wordpress_version' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'plugin_version' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];
    }
}
