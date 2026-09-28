<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonicalizes user-supplied website URLs and centralizes the SSRF rules
 * shared by validation (Phase 2) and outbound WordPress calls (Phase 3).
 */
class UrlNormalizer
{
    /**
     * Normalize a website URL to its canonical stored form.
     *
     * Rules: trim whitespace; prepend https:// when the scheme is missing;
     * scheme must be http/https; host lowercased (trailing dot removed);
     * reject userinfo, query strings and fragments; drop default ports;
     * remove the trailing slash; keep non-default ports and paths
     * (subdirectory WordPress installs); maximum 255 characters.
     *
     * @throws InvalidArgumentException When the URL is invalid.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('The website URL is required.');
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('The website URL must be a valid address such as https://example.com.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The website URL must start with http:// or https://.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('The website URL must not contain a username or password.');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The website URL must not contain a query string or fragment.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if ($host === '') {
            throw new InvalidArgumentException('The website URL must include a host name.');
        }

        if (preg_match('/^[a-z0-9._-]+$/i', $host) !== 1) {
            throw new InvalidArgumentException('The website URL must contain a valid host name.');
        }

        $normalized = $scheme.'://'.$host;

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $isDefaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);

        if ($port !== null && ! $isDefaultPort) {
            $normalized .= ':'.$port;
        }

        $normalized .= rtrim($parts['path'] ?? '', '/');

        if (strlen($normalized) > 255) {
            throw new InvalidArgumentException('The website URL must not exceed 255 characters.');
        }

        return $normalized;
    }

    /**
     * True when the host is a loopback/private/reserved literal IP or
     * "localhost". Host names are left alone here: DNS resolution happens
     * again immediately before each outbound request (Phase 3), which is also
     * where rebinding attacks must be caught.
     */
    public static function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Whether a stored URL may be targeted from the current environment.
     * Local and testing environments allow private hosts so a local WordPress
     * development site can be connected.
     */
    public static function isAllowedUrl(string $url): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' && ! self::isPrivateHost($host);
    }
}
