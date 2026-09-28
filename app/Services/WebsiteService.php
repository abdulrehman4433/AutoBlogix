<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebsiteStatus;
use App\Models\User;
use App\Models\Website;
use App\Support\UrlNormalizer;
use InvalidArgumentException;

/**
 * Website lifecycle business logic. Controllers stay thin; credentials are
 * issued through WebsiteCredentialService (never mass-assignable).
 */
class WebsiteService
{
    public function __construct(private readonly WebsiteCredentialService $credentialService) {}

    /**
     * Create a website with freshly issued credentials.
     *
     * @param  array{name: string, url: string, timezone?: string}  $attributes
     * @return array{website: Website, secret: string} Secret shown once.
     *
     * @throws InvalidArgumentException When the URL cannot be normalized.
     */
    public function create(User $user, array $attributes, ?string $ipAddress = null): array
    {
        $website = new Website;
        $website->user()->associate($user);
        $website->name = $attributes['name'];
        $website->url = UrlNormalizer::normalize($attributes['url']);
        $website->timezone = $attributes['timezone'] ?? 'UTC';
        $website->status = WebsiteStatus::Pending;
        $website->save();

        $secret = $this->credentialService->issueCredentials($website, $ipAddress);

        return ['website' => $website, 'secret' => $secret];
    }

    /**
     * Update editable attributes (name, url, timezone). Credentials and
     * connection status are untouched.
     *
     * @param  array{name?: string, url?: string, timezone?: string}  $attributes
     *
     * @throws InvalidArgumentException When the URL cannot be normalized.
     */
    public function update(Website $website, array $attributes): Website
    {
        if (array_key_exists('name', $attributes)) {
            $website->name = $attributes['name'];
        }

        if (array_key_exists('url', $attributes)) {
            $website->url = UrlNormalizer::normalize($attributes['url']);
        }

        if (array_key_exists('timezone', $attributes)) {
            $website->timezone = $attributes['timezone'];
        }

        $website->save();

        return $website;
    }

    /**
     * Delete a website; posts and logs cascade at the database level.
     */
    public function delete(Website $website): void
    {
        $website->delete();
    }
}
