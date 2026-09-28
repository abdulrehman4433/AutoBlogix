<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

/**
 * Owner-only access to websites, their settings and their credentials.
 * Auto-discovered by Laravel's convention (App\Models\Website → App\Policies\WebsitePolicy).
 */
class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        return $this->owns($user, $website);
    }

    public function update(User $user, Website $website): bool
    {
        return $this->owns($user, $website);
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->owns($user, $website);
    }

    public function rotateCredentials(User $user, Website $website): bool
    {
        return $this->owns($user, $website);
    }

    public function revokeCredentials(User $user, Website $website): bool
    {
        return $this->owns($user, $website);
    }

    private function owns(User $user, Website $website): bool
    {
        return $user->id === $website->user_id;
    }
}
