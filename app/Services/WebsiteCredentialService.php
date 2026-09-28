<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConnectionLogStatus;
use App\Enums\WebsiteStatus;
use App\Models\Website;
use Illuminate\Support\Str;

/**
 * Generates, issues, rotates, revokes and verifies WordPress API credentials.
 *
 * The API key is stored plain (unique, indexed). The API secret is stored with
 * Laravel's `encrypted` cast because HMAC verification requires the server to
 * know the plaintext secret. The secret is shown to the user exactly once —
 * at issuance or rotation — and never appears in URLs or log rows.
 */
class WebsiteCredentialService
{
    /**
     * API key format: abx_ + 32 lowercased random characters.
     */
    public function generateApiKey(): string
    {
        do {
            $key = 'abx_'.Str::lower(Str::random(32));
        } while (Website::where('api_key', $key)->exists());

        return $key;
    }

    /**
     * API secret format: abxs_ + 40 characters of cryptographically secure
     * randomness (160 bits). No uniqueness query is possible against an
     * encrypted column, and a collision is practically impossible.
     */
    public function generateApiSecret(): string
    {
        return 'abxs_'.bin2hex(random_bytes(20));
    }

    /**
     * Issue the first credential pair for a website (website creation).
     * Returns the plaintext secret so the controller can flash it once.
     */
    public function issueCredentials(Website $website, ?string $ipAddress = null): string
    {
        $secret = $this->assignNewPair($website);

        $website->status = WebsiteStatus::Pending;
        $website->save();

        $this->log($website, 'credentials_created', 'API credentials generated.', $ipAddress);

        return $secret;
    }

    /**
     * Rotate credentials: the new pair replaces the old one immediately, so
     * the previous key stops verifying on the plugin's next request. The
     * website returns to "pending" until the plugin receives the new pair.
     */
    public function rotateCredentials(Website $website, ?string $ipAddress = null): string
    {
        $secret = $this->assignNewPair($website);

        $website->status = WebsiteStatus::Pending;
        $website->save();

        $this->log($website, 'credentials_rotated', 'API credentials rotated. Previous credentials are no longer valid.', $ipAddress);

        return $secret;
    }

    /**
     * Revoke credentials: no pair remains, so the plugin cannot authenticate.
     */
    public function revokeCredentials(Website $website, ?string $ipAddress = null): void
    {
        $website->api_key = null;
        $website->encrypted_api_secret = null;
        $website->status = WebsiteStatus::Disconnected;
        $website->save();

        $this->log($website, 'credentials_revoked', 'API credentials revoked.', $ipAddress);
    }

    /**
     * Constant-time verification of an inbound key/secret pair (used by the
     * Phase 3 HMAC middleware). Returns the owning website on success.
     */
    public function verifyCredentials(string $apiKey, string $apiSecret): ?Website
    {
        if ($apiKey === '' || $apiSecret === '') {
            return null;
        }

        $website = Website::where('api_key', $apiKey)->first();

        if ($website === null || $website->encrypted_api_secret === null) {
            return null;
        }

        return hash_equals($website->encrypted_api_secret, $apiSecret) ? $website : null;
    }

    /**
     * Generate a fresh pair and store it on the website; returns plaintext.
     */
    private function assignNewPair(Website $website): string
    {
        $secret = $this->generateApiSecret();

        $website->api_key = $this->generateApiKey();
        $website->encrypted_api_secret = $secret;

        return $secret;
    }

    /**
     * Write a credential lifecycle event. Never logs key or secret material.
     */
    private function log(Website $website, string $action, string $message, ?string $ipAddress): void
    {
        $website->connectionLogs()->create([
            'action' => $action,
            'status' => ConnectionLogStatus::Success,
            'message' => $message,
            'ip_address' => $ipAddress,
        ]);
    }
}
