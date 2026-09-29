<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Built-in, deterministic provider: turns the input facts into a complete,
 * plausible post without any network call. It is the default for local
 * development, tests, and any account without an API key configured — its
 * output is fully predictable, which is what the test suite relies on.
 */
class DevelopmentAiProvider implements AiProviderInterface
{
    public function generate(string $systemPrompt, string $userPrompt, array $input): array
    {
        $title = $input['title'];
        $topic = trim((string) ($input['topic'] ?? '')) !== ''
            ? trim((string) $input['topic'])
            : $title;
        $tone = $input['tone'];
        $lengthWords = $input['length_words'];

        $keywords = array_values(array_filter(array_map(
            static fn (mixed $keyword): string => trim((string) $keyword),
            $input['keywords'],
        )));

        if ($keywords === []) {
            $keywords = array_slice(array_values(array_filter(
                array_map(
                    static fn (string $word): string => Str::lower($word),
                    preg_split('/\s+/', $title) ?: [],
                ),
                static fn (string $word): bool => Str::length($word) > 3,
            )), 0, 3);
        }

        $tags = array_slice($keywords, 0, 5);

        if ($tags === []) {
            $tags = array_values(array_filter(preg_split('/\s+/', $topic) ?: []));
        }

        $sections = max(2, min(6, (int) ceil($lengthWords / 300)));

        $introText = $title.' can feel overwhelming at first, but the essentials of '
            .$topic.' are straightforward. This post breaks the topic down in a '
            .$tone.' way, with practical steps you can apply right away.';

        $body = '<p>'.e($introText).'</p>';

        $usedKeywords = $keywords !== [] ? $keywords : [$topic];

        for ($i = 0; $i < $sections; $i++) {
            $keyword = $usedKeywords[$i % count($usedKeywords)];

            $body .= '<h2>'.e(Str::ucfirst($keyword)).' explained</h2>';
            $body .= '<p>When it comes to '.e($topic).', '.e($keyword)
                .' is where careful work pays off. A '.e($tone)
                .' approach keeps things practical: start with the goal in mind, '
                .'choose the steps that match your situation, and measure what '
                .'changes. Used well, '.e($keyword).' turns a vague plan about '
                .e($topic).' into something you can act on today.</p>';
        }

        $body .= '<h2>Next steps</h2>';
        $body .= '<p>'.e($title).' does not need to be complicated. Pick one idea '
            .'from above, put it into practice this week, and build on what works. '
            .'If '.e($topic).' matters to you, now is the right time to begin.</p>';

        return [
            'content' => $body,
            'excerpt' => Str::limit($introText, 200),
            'tags' => $tags,
            'keywords' => $keywords,
            'meta_description' => Str::limit($title.' — a '.$tone.' guide to '.$topic.'.', 155),
            'tokens_used' => null,
        ];
    }

    public function name(): string
    {
        return 'development';
    }

    public function model(): ?string
    {
        return null;
    }
}
