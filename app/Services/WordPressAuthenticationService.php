<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConnectionLogStatus;
use App\Exceptions\WordPressAuthenticationException;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Verifies inbound WordPress API requests: header presence and format,
 * ±5 minute timestamp window, key lookup, HMAC signature and single-use
 * nonce (atomic cache lock).
 */
class WordPressAuthenticationService
{
    /**
     * Allowed clock skew between plugin and AutoBlogix, in seconds.
     */
    public const int MAX_SKEW_SECONDS = 300;

    /**
     * Nonce retention: timestamp window (300 s) plus an equal grace period,
     * so a replayed signed request stays rejected even at the window edge.
     */
    public const int NONCE_TTL_SECONDS = 600;

    public function __construct(
        private readonly HmacSigner $signer,
        private readonly WebsiteCredentialService $credentialService,
    ) {}

    /**
     * Authenticate a signed request and return the owning website.
     *
     * @throws WordPressAuthenticationException With the documented stable
     *                                          error code for every rejection.
     */
    public function authenticate(Request $request): Website
    {
        $key = trim((string) $request->header('X-ABX-Key'));
        $timestamp = trim((string) $request->header('X-ABX-Timestamp'));
        $nonce = trim((string) $request->header('X-ABX-Nonce'));
        $signature = trim((string) $request->header('X-ABX-Signature'));

        if ($key === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            throw new WordPressAuthenticationException(
                'missing_headers',
                'One or more authentication headers are missing.',
            );
        }

        if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1) {
            throw new WordPressAuthenticationException(
                'invalid_nonce',
                'The X-ABX-Nonce header must be 16 to 128 characters of letters, digits, "-" or "_".',
            );
        }

        $website = $this->resolveWebsite($key);

        if ($website === null) {
            throw new WordPressAuthenticationException(
                'unknown_key',
                'The API key is unknown or has been revoked.',
            );
        }

        if (
            preg_match('/^\d{10}$/', $timestamp) !== 1
            || abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS
        ) {
            $this->logFailure($website, 'Request timestamp outside the allowed window.', $request->ip());

            throw new WordPressAuthenticationException(
                'stale_timestamp',
                'The X-ABX-Timestamp header must be within 5 minutes of server time.',
            );
        }

        $secret = $website->encrypted_api_secret;

        if ($secret === null) {
            throw new WordPressAuthenticationException(
                'unknown_key',
                'The API key is unknown or has been revoked.',
            );
        }

        if (! $this->signer->verify(
            $request->getMethod(),
            $request->getPathInfo(),
            $timestamp,
            $nonce,
            $request->getContent(),
            $secret,
            $signature,
        )) {
            $this->logFailure($website, 'Request signature verification failed.', $request->ip());

            throw new WordPressAuthenticationException(
                'invalid_signature',
                'The request signature does not match the computed signature.',
            );
        }

        // Consume the nonce only after the signature proved authentic so
        // unsigned traffic cannot poison the nonce cache. Cache::add is
        // atomic on every store: false means this exact nonce was seen before.
        if (! Cache::add($this->nonceKey($key, $nonce), true, self::NONCE_TTL_SECONDS)) {
            $this->logFailure($website, 'Replayed nonce detected.', $request->ip());

            throw new WordPressAuthenticationException(
                'replayed_nonce',
                'This signed request has already been received (nonce reuse).',
            );
        }

        return $website;
    }

    /**
     * Resolve the API key to a website, performing a dummy HMAC first for
     * unknown keys so response timing does not reveal whether a key exists.
     */
    private function resolveWebsite(string $key): ?Website
    {
        $website = str_starts_with($key, 'abx_')
            ? $this->credentialService->findWebsiteByKey($key)
            : null;

        if ($website === null || $website->encrypted_api_secret === null) {
            hash_hmac('sha256', $key, 'dummy-secret-timing-equalization-key');

            return null;
        }

        return $website;
    }

    private function nonceKey(string $key, string $nonce): string
    {
        return 'wp-nonce:'.$key.':'.$nonce;
    }

    /**
     * Record an authentication failure for a known key. Never logs the
     * secret, signature or nonce value.
     */
    private function logFailure(Website $website, string $message, ?string $ipAddress): void
    {
        $website->connectionLogs()->create([
            'action' => 'authenticate',
            'status' => ConnectionLogStatus::Failed,
            'message' => $message,
            'ip_address' => $ipAddress,
        ]);
    }
}
