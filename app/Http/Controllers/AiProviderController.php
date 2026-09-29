<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiProviderRequest;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * AI Providers: per-user API configurations. The owner-scoped route bind
 * means another user's row ID resolves to 404 — there is no other access
 * path, so no separate policy is needed. The API key is never rendered
 * back (masked hint only).
 */
class AiProviderController extends Controller
{
    /**
     * List the user's provider configurations.
     */
    public function show(Request $request): View
    {
        return view('ai-providers.index', [
            'providers' => $request->user()->aiProviders()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(),
            'environmentProvider' => (string) config('ai.provider'),
        ]);
    }

    /**
     * Save a configuration; the new row becomes the single active one.
     */
    public function store(StoreAiProviderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($user, $data): void {
            AiProvider::query()
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);

            $provider = new AiProvider;
            $provider->provider = 'openai';
            $provider->api_key = $data['api_key'];
            $provider->model = $data['model'] ?? null;
            $provider->base_url = $data['base_url'] ?? null;
            $provider->is_active = true;
            $user->aiProviders()->save($provider);
        });

        return redirect()->route('ai.providers')->with(
            'success',
            'AI provider saved and activated. The key is stored encrypted and shown only as a masked hint.',
        );
    }

    /**
     * Make a stored configuration the active one.
     */
    public function activate(AiProvider $provider): RedirectResponse
    {
        DB::transaction(function () use ($provider): void {
            AiProvider::query()
                ->where('user_id', $provider->user_id)
                ->update(['is_active' => false]);

            $provider->is_active = true;
            $provider->save();
        });

        return redirect()->route('ai.providers')->with('success', 'AI provider activated.');
    }

    /**
     * Remove a configuration (falls back to the environment provider).
     */
    public function destroy(AiProvider $provider): RedirectResponse
    {
        $wasActive = $provider->is_active;
        $provider->delete();

        return redirect()->route('ai.providers')->with(
            'success',
            $wasActive
                ? 'AI provider removed. AutoBlogix now uses the environment provider ('
                    .'AI_PROVIDER='.config('ai.provider').').'
                : 'AI provider removed.',
        );
    }
}
