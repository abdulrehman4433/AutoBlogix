<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for the AutoBlogix HMAC-SHA256 request signature,
 * used identically for inbound (plugin → AutoBlogix) and outbound
 * (AutoBlogix → plugin) requests.
 *
 * payload = METHOD "\n" PATH "\n" TIMESTAMP "\n" NONCE "\n" hex(sha256(body))
 * signature = hex(hmac_sha256(secret, payload))
 */
class HmacSigner
{
    /**
     * Build the canonical string that gets signed.
     */
    public function payload(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    /**
     * Compute the hex HMAC-SHA256 signature for a request.
     */
    public function sign(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
        string $secret,
    ): string {
        return hash_hmac(
            'sha256',
            $this->payload($method, $path, $timestamp, $nonce, $body),
            $secret,
        );
    }

    /**
     * Constant-time signature comparison.
     */
    public function verify(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
        string $secret,
        string $signature,
    ): bool {
        return hash_equals(
            $this->sign($method, $path, $timestamp, $nonce, $body, $secret),
            $signature,
        );
    }
}
