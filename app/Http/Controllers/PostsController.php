<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StorePostRequest;
use App\Models\BlogPost;
use App\Services\BlogPostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostsController extends Controller
{
    public function __construct(private readonly BlogPostService $postService) {}

    public function index(Request $request): View
    {
        $websites = $request->user()->websites()->orderBy('name')->get();

        $query = $request->user()->blogPosts()->with('website')->latest();

        // Invalid filter values are ignored, never a 422 on a GET.
        $status = PostStatus::tryFrom((string) $request->query('status', ''));

        if ($status !== null) {
            $query->where('status', $status);
        }

        $websiteFilter = $request->query('website');

        if ($websiteFilter !== null && $websiteFilter !== '' && $websites->contains('id', (int) $websiteFilter)) {
            $query->where('website_id', (int) $websiteFilter);
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('topic', 'like', $like);
            });
        }

        return view('posts.index', [
            'posts' => $query->paginate(15)->withQueryString(),
            'websites' => $websites,
            'statuses' => PostStatus::cases(),
            'filters' => $request->only(['q', 'status', 'website']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('posts.create', [
            'websites' => $request->user()->websites()->orderBy('name')->get(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = $this->postService->create($request->user(), $request->validated());

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Post created as '.$post->status->label().'.');
    }

    public function show(Request $request, BlogPost $post): View
    {
        Gate::authorize('view', $post);

        return view('posts.show', [
            'post' => $post,
            'publishingLogs' => $post->publishingLogs()->latest('id')->get(),
        ]);
    }

    public function edit(Request $request, BlogPost $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.edit', [
            'post' => $post,
            'websites' => $request->user()->websites()->orderBy('name')->get(),
        ]);
    }

    public function update(StorePostRequest $request, BlogPost $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $this->postService->update($post, $request->validated());

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Post updated.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $this->postService->delete($post);

        return redirect()
            ->route('posts.index')
            ->with('success', 'Post deleted. Its publishing logs were removed with it.');
    }
}
