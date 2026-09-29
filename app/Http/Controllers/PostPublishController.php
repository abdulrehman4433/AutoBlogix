<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\PublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PostPublishController extends Controller
{
    public function __construct(private readonly PublishingService $publishing) {}

    /**
     * "Publish now" / "Retry publish" — one entry point; the service decides
     * whether the post may enter the publishing state (idempotency rules).
     */
    public function store(BlogPost $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $outcome = $this->publishing->requestPublish($post);

        $message = match ($outcome) {
            'queued' => 'Publishing started. The post goes live as soon as the queue worker finishes.',
            'already_publishing' => 'This post is already being published.',
            'published' => 'This post is already published.',
            default => 'This post cannot be published right now.',
        };

        return redirect()->route('posts.show', $post)->with('success', $message);
    }
}
