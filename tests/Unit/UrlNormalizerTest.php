<?php

namespace Tests\Unit;

use App\Support\UrlNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

class UrlNormalizerTest extends TestCase
{
    public function test_prepends_https_scheme_when_missing(): void
    {
        $this->assertSame('https://example.com', UrlNormalizer::normalize('example.com'));
    }

    public function test_lowercases_host_and_strips_trailing_slash(): void
    {
        $this->assertSame('https://example.com', UrlNormalizer::normalize('https://Example.COM/'));
    }

    public function test_keeps_subdirectory_path(): void
    {
        $this->assertSame('https://example.com/blog', UrlNormalizer::normalize('https://example.com/blog/'));
    }

    public function test_drops_default_port_and_keeps_custom_port(): void
    {
        $this->assertSame('https://example.com', UrlNormalizer::normalize('https://example.com:443'));
        $this->assertSame('http://example.com:8080', UrlNormalizer::normalize('http://example.com:8080'));
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('http:// or https://');

        UrlNormalizer::normalize('ftp://example.com');
    }

    public function test_rejects_userinfo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('username or password');

        UrlNormalizer::normalize('https://user:pass@example.com');
    }

    public function test_rejects_query_string_and_fragment(): void
    {
        try {
            UrlNormalizer::normalize('https://example.com/?a=1');
            $this->fail('Query string should be rejected.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('query string or fragment');

        UrlNormalizer::normalize('https://example.com/#section');
    }

    public function test_rejects_empty_and_whitespace_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UrlNormalizer::normalize('   ');
    }

    public function test_rejects_invalid_host_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid host name');

        UrlNormalizer::normalize('https://exa mple.com');
    }

    public function test_rejects_urls_longer_than_255_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('255');

        UrlNormalizer::normalize('https://example.com/'.str_repeat('a', 260));
    }

    public function test_detects_private_loopback_and_reserved_hosts(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '172.16.0.1', '169.254.1.1', 'localhost', 'api.localhost', '::1', '[::1]'] as $host) {
            $this->assertTrue(UrlNormalizer::isPrivateHost($host), "Expected {$host} to be private.");
        }
    }

    public function test_allows_public_ips_and_hostnames(): void
    {
        foreach (['8.8.8.8', 'example.com', 'blog.internal'] as $host) {
            $this->assertFalse(UrlNormalizer::isPrivateHost($host), "Expected {$host} to be allowed.");
        }
    }

    public function test_private_urls_are_allowed_in_local_environments(): void
    {
        // Tests run in the "testing" environment, where local WordPress dev is permitted.
        $this->assertTrue(UrlNormalizer::isAllowedUrl('http://127.0.0.1:8080'));
        $this->assertTrue(UrlNormalizer::isAllowedUrl('http://localhost'));
    }

    public function test_private_urls_are_blocked_outside_local_environments(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->assertFalse(UrlNormalizer::isAllowedUrl('http://127.0.0.1'));
            $this->assertFalse(UrlNormalizer::isAllowedUrl('http://192.168.1.10'));
            $this->assertFalse(UrlNormalizer::isAllowedUrl('http://localhost'));
            $this->assertTrue(UrlNormalizer::isAllowedUrl('https://example.com'));
        } finally {
            $this->app['env'] = $previous;
        }
    }
}
