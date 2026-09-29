<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePromptTemplatesRequest;
use App\Models\PromptTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings: account summary, the global AI prompt template editor (the
 * Phase 6 handoff — `prompt_templates` are global rows with a config
 * fallback), and a read-only system card. Profile editing stays with
 * Breeze's profile.* routes.
 */
class SettingsController extends Controller
{
    private const string PROMPT_KEY = 'blog_post_generation';

    public function index(Request $request): View
    {
        return view('settings.index', [
            'account' => $request->user(),
            'prompt' => PromptTemplate::resolve(self::PROMPT_KEY),
            'system' => [
                'Application URL' => (string) config('app.url'),
                'Timezone' => (string) config('app.timezone'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Queue connection' => (string) config('queue.default'),
                'Cache store' => (string) config('cache.default'),
                'Session driver' => (string) config('session.driver'),
                'Environment AI provider' => (string) (config('ai.provider') ?: 'not set'),
                'Environment AI key' => filled(config('ai.api_key')) ? 'Configured (hidden)' : 'Not set',
            ],
        ]);
    }

    /**
     * Save the template (create or edit the single global row). The name
     * column keeps its existing/config value — the editor only owns the
     * two prompt texts.
     */
    public function updatePrompts(UpdatePromptTemplatesRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $name = PromptTemplate::query()->where('key', self::PROMPT_KEY)->value('name')
            ?? (string) config('ai.defaults.blog_post_generation.name', 'Blog post generation');

        PromptTemplate::query()->updateOrCreate(
            ['key' => self::PROMPT_KEY],
            [
                'name' => $name,
                'system_prompt' => $data['system_prompt'],
                'user_prompt' => $data['user_prompt'],
            ],
        );

        return redirect()
            ->route('settings.index')
            ->with('success', 'Prompt template saved. AI generation now uses this text.');
    }

    /**
     * Delete the row — PromptTemplate::resolve() transparently falls back
     * to config('ai.defaults.*'), so generation is never left without a
     * prompt.
     */
    public function resetPrompts(): RedirectResponse
    {
        PromptTemplate::query()->where('key', self::PROMPT_KEY)->delete();

        return redirect()
            ->route('settings.index')
            ->with('success', 'Prompt template reset to the built-in default.');
    }
}
