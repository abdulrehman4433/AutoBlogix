<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal allowlist sanitizer for rendering post content in the UI. Content
 * may be authored by the user or produced by an AI provider, so previews are
 * never rendered raw: non-allowlisted tags are dropped, inline event
 * handlers (`on*`) are stripped, and script-capable URL schemes in
 * href/src are neutralized.
 *
 * Flagged improvement: swap in a dedicated HTML purifier package if
 * rich-HTML needs grow beyond this allowlist.
 */
class HtmlSanitizer
{
    private const string ALLOWED_TAGS = '<p><br><a><strong><em><b><i><u><s><mark>'
        .'<h1><h2><h3><h4><h5><h6>'
        .'<ul><ol><li><dl><dt><dd>'
        .'<blockquote><pre><code><kbd><samp><var>'
        .'<img><figure><figcaption><hr>'
        .'<table><thead><tbody><tfoot><tr><th><td><caption>'
        .'<span><div><small><sub><sup><del><ins><abbr><cite><q>';

    private const string DANGEROUS_SCHEMES = 'javascript:,vbscript:,data:text/html';

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Drop inline event handlers: onclick=, onerror=, ...
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        // Neutralize script-capable URL schemes while keeping the attribute.
        $clean = preg_replace_callback(
            '/\s(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            static function (array $matches): string {
                $value = trim($matches[3] ?? '');
                $value = $value !== '' ? $value : trim($matches[4] ?? '');
                $value = $value !== '' ? $value : trim($matches[5] ?? '');

                $scheme = strtolower(preg_replace('/\s+/', '', html_entity_decode($value)) ?? '');

                foreach (explode(',', self::DANGEROUS_SCHEMES) as $dangerous) {
                    if (str_starts_with($scheme, $dangerous)) {
                        return '';
                    }
                }

                return $matches[0];
            },
            $clean,
        ) ?? $clean;

        return $clean;
    }
}
