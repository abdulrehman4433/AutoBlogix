<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiContentRequest;
use App\Models\BlogPost;
use App\Services\AiContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * AI Content: the composer page (create + generate in one step) and the
 * per-post generate/regenerate action. The service owns every status rule.
 */
class AiContentController extends Controller
{
    public function __construct(private readonly AiContentService $ai) {}

    /**
     * Show the composer.
     */
    public function show(Request $request): View
    {
        return view('ai-content.index', [
            'websites' => $request->user()->websites()->orderBy('name')->get(),
            'tones' => AiContentService::TONES,
            'lengths' => AiContentService::LENGTHS,
        ]);
    }

    /**
     * Create a draft from the composer and generate its content.
     */
    public function store(StoreAiContentRequest $request): RedirectResponse
    {
        $result = $this->ai->compose($request->user(), $request->validated());

        return redirect()
            ->route('posts.show', $result['post'])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Generate content for an existing post (show-page action).
     */
    public function generate(Request $request, BlogPost $post): RedirectResponse
    {
        Gate::authorize('generate', $post);

        $result = $this->ai->generateForPost($post);

        return redirect()
            ->route('posts.show', $post)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
