<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\ConnectionLog;
use App\Models\PublishingLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    /**
     * Build every metric the dashboard needs for one user in a bounded number
     * of aggregate queries (no full-table loads, no cross-user leakage).
     *
     * @return array{
     *     websites_total: int,
     *     websites_connected: int,
     *     posts_total: int,
     *     posts_by_status: array<string, int>,
     *     recent_publishing: Collection<int, PublishingLog>,
     *     upcoming_posts: Collection<int, BlogPost>,
     *     recent_connections: Collection<int, ConnectionLog>,
     * }
     */
    public function metricsFor(User $user): array
    {
        return [
            'websites_total' => Website::where('user_id', $user->id)->count(),
            'websites_connected' => Website::where('user_id', $user->id)
                ->where('status', 'connected')
                ->count(),
            'posts_total' => BlogPost::where('user_id', $user->id)->count(),
            'posts_by_status' => $this->postCountsByStatus($user),
            'recent_publishing' => $this->recentPublishing($user),
            'upcoming_posts' => $this->upcomingPosts($user),
            'recent_connections' => $this->recentConnections($user),
        ];
    }

    /**
     * Count the user's posts per status, keyed by status value with a 0 default
     * for every status.
     *
     * @return array<string, int>
     */
    private function postCountsByStatus(User $user): array
    {
        $counts = array_fill_keys(array_column(PostStatus::cases(), 'value'), 0);

        $rows = BlogPost::query()
            ->where('user_id', $user->id)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        foreach ($rows as $status => $total) {
            $counts[$status] = (int) $total;
        }

        return $counts;
    }

    /**
     * @return Collection<int, PublishingLog>
     */
    private function recentPublishing(User $user): Collection
    {
        return PublishingLog::query()
            ->whereHas('website', fn ($query) => $query->where('user_id', $user->id))
            ->with(['post:id,title,status', 'website:id,name'])
            ->latest()
            ->take(5)
            ->get();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function upcomingPosts(User $user): Collection
    {
        return BlogPost::query()
            ->where('user_id', $user->id)
            ->where('status', PostStatus::Scheduled->value)
            ->where('scheduled_at', '>=', now())
            ->with('website:id,name')
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();
    }

    /**
     * @return Collection<int, ConnectionLog>
     */
    private function recentConnections(User $user): Collection
    {
        return ConnectionLog::query()
            ->whereHas('website', fn ($query) => $query->where('user_id', $user->id))
            ->with('website:id,name')
            ->latest()
            ->take(5)
            ->get();
    }
}
