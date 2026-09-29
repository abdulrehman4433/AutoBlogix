<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

/**
 * Owner-only access to posts. Auto-discovered by Laravel's convention
 * (App\Models\BlogPost → App\Policies\BlogPostPolicy). The owner-scoped
 * Route::bind('post') already hides foreign posts (404); this policy is the
 * second layer.
 */
class BlogPostPolicy
{
    public function view(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post);
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post);
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post);
    }

    private function owns(User $user, BlogPost $post): bool
    {
        return $user->id === $post->user_id;
    }
}
