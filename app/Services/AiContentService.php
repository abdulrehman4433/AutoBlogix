<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogStatus;
use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Exceptions\AiProviderException;
use App\Models\AiLog;
use App\Models\BlogPost;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI content generation: composer (create + fill) and per-post
 * generate/regenerate. Status transitions follow the Phase 6 rules —
 * draft | generated | generating may generate, success ends `generated`,
 * every failure restores the previous status and records itself as data
 * (ai_logs) with the technical detail kept off the post.
 */
class AiContentService
{
    /**
     * Tone options: value => label.
     *
     * @var array<string, string>
     */
    public const array TONES = [
        'friendly' => 'Friendly',
        'professional' => 'Professional',
        'casual' => 'Casual',
        'authoritative' => 'Authoritative',
    ];

    /**
     * Length options: value => label.
     *
     * @var array<string, string>
     */
    public const array LENGTHS = [
        'short' => 'Short (~400 words)',
        'medium' => 'Medium (~800 words)',
        'long' => 'Long (~1500 words)',
    ];

    /**
     * Word targets behind the length labels.
     *
     * @var array<string, int>
     */
    public const array LENGTH_WORDS = [
        'short' => 400,
        'medium' => 800,
        'long' => 1500,
    ];

    public const string PROMPT_KEY = 'blog_post_generation';

    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly BlogPostService $posts,
    ) {}

    /**
     * Create a draft from composer input, then generate its content.
     *
     * @param  array<string, mixed>  $attributes  Validated composer input.
     * @return array{post: BlogPost, ok: bool, code: string, message: string}
     */
    public function compose(User $user, array $attributes): array
    {
        $post = $this->posts->create($user, [
            'website_id' => $attributes['website_id'],
            'title' => $attributes['title'],
            'topic' => $attributes['topic'] ?? null,
            'keywords' => array_values((array) ($attributes['keywords'] ?? [])),
        ], PostSource::Ai);

        $result = $this->generateForPost($post, [
            'tone' => (string) ($attributes['tone'] ?? 'professional'),
            'length_words' => self::LENGTH_WORDS[(string) ($attributes['length'] ?? 'medium')]
                ?? self::LENGTH_WORDS['medium'],
        ]);

        return ['post' => $post, ...$result];
    }

    /**
     * Run one generation attempt for an existing post. Never throws:
     * every failure mode ends as data — restored status, closed log,
     * friendly message for the flash.
     *
     * @param  array{tone?: string, length_words?: int}  $options
     * @return array{ok: bool, code: string, message: string}
     */
    public function generateForPost(BlogPost $post, array $options = []): array
    {
        /** @var array{allowed: bool, post: BlogPost|null, previous: PostStatus|null, log: AiLog|null}|null $entered */
        $entered = null;

        try {
            $provider = $this->providers->forUser($post->user);

            $entered = DB::transaction(function () use ($post, $provider): array {
                $locked = BlogPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, [PostStatus::Draft, PostStatus::Generated, PostStatus::Generating], true)) {
                    return ['allowed' => false, 'post' => null, 'previous' => null, 'log' => null];
                }

                $this->closeInterruptedAttempts($locked);

                $previous = $locked->status;
                $locked->status = PostStatus::Generating;
                $locked->save();

                $log = $locked->aiLogs()->create([
                    'user_id' => $locked->user_id,
                    'prompt_key' => self::PROMPT_KEY,
                    'provider' => $provider->name(),
                    'model' => $provider->model(),
                    'status' => AiLogStatus::Processing,
                    'started_at' => now(),
                ]);

                return ['allowed' => true, 'post' => $locked, 'previous' => $previous, 'log' => $log];
            });

            if (! $entered['allowed']) {
                return [
                    'ok' => false,
                    'code' => 'not_allowed',
                    'message' => 'AI generation only works on draft or generated posts.',
                ];
            }

            /** @var BlogPost $locked */
            $locked = $entered['post'];
            /** @var AiLog $log */
            $log = $entered['log'];

            $template = PromptTemplate::resolve(self::PROMPT_KEY);

            $input = [
                'title' => $locked->title,
                'topic' => $locked->topic,
                'keywords' => array_values((array) ($locked->keywords ?? [])),
                'tone' => (string) ($options['tone'] ?? 'professional'),
                'length_words' => (int) ($options['length_words'] ?? self::LENGTH_WORDS['medium']),
            ];

            $output = $provider->generate(
                $template['system'],
                $this->renderPrompt($template['user'], $input),
                $input,
            );

            $content = trim((string) ($output['content'] ?? ''));

            if ($content === '') {
                throw new AiProviderException(
                    'The AI returned an empty post. Try again.',
                    'empty content returned for post '.$locked->id,
                );
            }

            $locked->content = HtmlSanitizer::sanitize($content);
            $locked->excerpt = trim((string) ($output['excerpt'] ?? ''));
            $locked->tags = $output['tags'] ?? [];
            $locked->keywords = $output['keywords'] ?? [];
            $locked->meta_description = $output['meta_description'] ?? null;
            $locked->ai_provider = $provider->name();
            $locked->ai_model = $provider->model();
            $locked->status = PostStatus::Generated;
            $locked->save();

            $log->status = AiLogStatus::Success;
            $log->tokens_used = $output['tokens_used'] ?? null;
            $log->completed_at = now();
            $log->save();

            return [
                'ok' => true,
                'code' => 'generated',
                'message' => 'AI content generated — review it below, then publish or schedule.',
            ];
        } catch (AiProviderException $exception) {
            $this->rollbackAttempt($entered, $exception->technical ?? $exception->friendly);

            Log::warning('AI generation failed', [
                'post_id' => $post->id,
                'technical' => $exception->technical,
            ]);

            return ['ok' => false, 'code' => 'provider_failed', 'message' => $exception->friendly];
        } catch (\Throwable $exception) {
            $this->rollbackAttempt($entered, $exception::class.': '.$exception->getMessage());

            Log::error('AI generation crashed', [
                'post_id' => $post->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 'provider_failed',
                'message' => 'AI generation failed unexpectedly. Try again.',
            ];
        }
    }

    /**
     * Substitute :placeholders in the prompt's user message.
     *
     * @param  array{
     *     title: string,
     *     topic: ?string,
     *     keywords: array<int, string>,
     *     tone: string,
     *     length_words: int,
     * }  $input
     */
    private function renderPrompt(string $template, array $input): string
    {
        $topic = $input['topic'] !== null && $input['topic'] !== ''
            ? $input['topic']
            : $input['title'];

        return strtr($template, [
            ':title' => $input['title'],
            ':topic' => $topic,
            ':keywords' => $input['keywords'] !== []
                ? implode(', ', $input['keywords'])
                : '(choose sensible keywords from the title)',
            ':tone' => $input['tone'],
            ':length_words' => (string) $input['length_words'],
        ]);
    }

    /**
     * A post visible in `generating` means a previous synchronous attempt
     * died — close its open log rows so the history stays truthful.
     */
    private function closeInterruptedAttempts(BlogPost $post): void
    {
        $interrupted = $post->aiLogs()
            ->where('status', AiLogStatus::Processing)
            ->get();

        foreach ($interrupted as $log) {
            $log->status = AiLogStatus::Failed;
            $log->error_message = 'Generation interrupted before it finished.';
            $log->completed_at = now();
            $log->save();
        }
    }

    /**
     * Restore the status and close the attempt log after a failure; a
     * no-op when nothing had started yet (provider resolution failures).
     *
     * @param  array{allowed: bool, post: BlogPost|null, previous: PostStatus|null, log: AiLog|null}|null  $entered
     */
    private function rollbackAttempt(?array $entered, ?string $technical): void
    {
        if ($entered === null || ! $entered['allowed']) {
            return;
        }

        DB::transaction(function () use ($entered, $technical): void {
            $post = BlogPost::query()->whereKey($entered['post']->id)->lockForUpdate()->first();

            if ($post !== null && $post->status === PostStatus::Generating) {
                $post->status = $entered['previous'];
                $post->save();
            }

            $log = AiLog::query()->whereKey($entered['log']->id)->lockForUpdate()->first();

            if ($log !== null && $log->status === AiLogStatus::Processing) {
                $log->status = AiLogStatus::Failed;
                $log->error_message = $technical ?? 'Generation failed.';
                $log->completed_at = now();
                $log->save();
            }
        });
    }
}
