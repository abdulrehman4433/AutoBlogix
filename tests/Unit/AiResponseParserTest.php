<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\AiResponseParser;
use PHPUnit\Framework\TestCase;

class AiResponseParserTest extends TestCase
{
    public function test_fenced_json_block_is_extracted(): void
    {
        $raw = "Here is the JSON you asked for:\n```json\n"
            .json_encode([
                'content' => '<p>Body</p>',
                'excerpt' => 'Sum',
                'tags' => ['a'],
                'keywords' => ['b'],
                'meta_description' => 'Meta',
            ])
            ."\n```\nEnjoy!";

        $parsed = AiResponseParser::parse($raw);

        $this->assertSame('<p>Body</p>', $parsed['content']);
        $this->assertSame('Sum', $parsed['excerpt']);
        $this->assertSame(['a'], $parsed['tags']);
        $this->assertSame(['b'], $parsed['keywords']);
        $this->assertSame('Meta', $parsed['meta_description']);
        $this->assertNull($parsed['tokens_used']);
    }

    public function test_json_object_surrounded_by_prose_is_extracted(): void
    {
        $raw = 'Sure! {"content":"<p>Body</p>"} — anything else?';

        $parsed = AiResponseParser::parse($raw);

        $this->assertSame('<p>Body</p>', $parsed['content']);
    }

    public function test_invalid_json_throws_friendly_exception_with_technical_detail(): void
    {
        try {
            AiResponseParser::parse('definitely not json');

            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $exception) {
            $this->assertSame('The AI returned an unexpected response format. Try again.', $exception->friendly);
            $this->assertNotNull($exception->technical);
        }
    }

    public function test_missing_or_empty_content_throws(): void
    {
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('The AI returned an empty post. Try again.');

        AiResponseParser::parse(json_encode(['excerpt' => 'no body']));
    }

    public function test_content_html_alias_is_accepted_and_excerpt_is_derived_when_missing(): void
    {
        $parsed = AiResponseParser::parse(json_encode([
            'content_html' => '<p>First paragraph text that is long enough to summarize.</p>',
        ]));

        $this->assertStringContainsString('First paragraph text', $parsed['content']);
        $this->assertStringContainsString('First paragraph text', $parsed['excerpt']);
        $this->assertNull($parsed['meta_description']);
    }

    public function test_tags_and_keywords_are_normalized(): void
    {
        $parsed = AiResponseParser::parse(json_encode([
            'content' => '<p>Body</p>',
            // Comma-separated string instead of an array.
            'tags' => 'alpha, beta ,, alpha',
            // Jumbo list with junk types, duplicates and an over-long entry.
            'keywords' => [
                1,
                2.5,
                null,
                ['nested'],
                'ok',
                'ok',
                str_repeat('x', 80),
                'k2',
                'k3',
                'k4',
                'k5',
                'k6',
                'k7',
                'k8',
                'k9',
                'k10',
                'k11',
                'k12',
            ],
            'meta_description' => str_repeat('m', 400),
        ]));

        $this->assertSame(['alpha', 'beta'], $parsed['tags']);

        // Capped at 10, de-duplicated, junk dropped, over-long truncated to 50.
        $this->assertCount(10, $parsed['keywords']);
        $this->assertSame('1', $parsed['keywords'][0]);
        $this->assertSame('2.5', $parsed['keywords'][1]);
        $this->assertContains('ok', $parsed['keywords']);
        $this->assertSame(1, count(array_keys($parsed['keywords'], 'ok', true)));
        $this->assertSame('', substr($parsed['keywords'][3], 50));

        $this->assertSame(300, strlen((string) $parsed['meta_description']));
    }

    public function test_non_list_values_become_empty_arrays(): void
    {
        $parsed = AiResponseParser::parse(json_encode([
            'content' => '<p>Body</p>',
            'tags' => 42,
            'keywords' => ['not' => 'a list shape is still iterable values'],
        ]));

        $this->assertSame([], $parsed['tags']);
        $this->assertIsArray($parsed['keywords']);
    }
}
