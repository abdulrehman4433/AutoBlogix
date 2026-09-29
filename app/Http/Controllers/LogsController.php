<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiLogStatus;
use App\Enums\ConnectionLogStatus;
use App\Enums\PublishingLogStatus;
use App\Models\BlogPost;
use App\Models\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One user-scoped activity feed across connection_logs + publishing_logs +
 * ai_logs. Three UNION ALL branches (only the selected types are built),
 * each already scoped and status-filtered, ordered by time and paginated.
 * The per-entity detail tables on website/post show pages stay as they
 * are — this is the cross-type overview.
 */
class LogsController extends Controller
{
    /** @var list<string> */
    private const array TYPES = ['connection', 'publishing', 'ai'];

    /** @var list<string> */
    private const array STATUSES = ['success', 'failed', 'pending', 'processing'];

    public function index(Request $request): View
    {
        // Invalid filter values are ignored, never a 422 on a GET.
        $requestedType = (string) $request->query('type', '');
        $type = in_array($requestedType, self::TYPES, true) ? $requestedType : null;

        $requestedStatus = (string) $request->query('status', '');
        $status = in_array($requestedStatus, self::STATUSES, true) ? $requestedStatus : null;

        $branches = $this->branches((int) $request->user()->id, $type, $status);

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $rows = DB::query()
            ->fromSub($union, 'activity')
            ->orderByDesc('happened_at')
            ->orderByDesc('row_id')
            ->paginate(15)
            ->withQueryString();

        $this->enrich($rows);

        return view('logs.index', [
            'rows' => $rows,
            'type' => $type,
            'status' => $status,
        ]);
    }

    /**
     * The selected feed branches with their uniform column shape:
     * type, subject_id, kind, status, detail, happened_at, row_id.
     *
     * @return array<int, Builder>
     */
    private function branches(int $userId, ?string $type, ?string $status): array
    {
        $branches = [];

        if ($type === null || $type === 'connection') {
            $branches[] = DB::table('connection_logs')
                ->whereIn('website_id', function ($query) use ($userId): void {
                    $query->select('id')->from('websites')->where('user_id', $userId);
                })
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->selectRaw("'connection' as type, website_id as subject_id, action as kind, status, message as detail, created_at as happened_at, id as row_id");
        }

        if ($type === null || $type === 'publishing') {
            $branches[] = DB::table('publishing_logs')
                ->whereIn('post_id', function ($query) use ($userId): void {
                    $query->select('id')->from('blog_posts')->where('user_id', $userId);
                })
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->selectRaw("'publishing' as type, post_id as subject_id, attempt as kind, status, COALESCE(response_summary, error_message) as detail, COALESCE(started_at, created_at) as happened_at, id as row_id");
        }

        if ($type === null || $type === 'ai') {
            $branches[] = DB::table('ai_logs')
                ->where('user_id', $userId)
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->selectRaw("'ai' as type, post_id as subject_id, provider as kind, status, error_message as detail, COALESCE(started_at, created_at) as happened_at, id as row_id");
        }

        return $branches;
    }

    /**
     * Turn the raw page rows into display-ready rows: subject names/links
     * (two batched queries, never N+1), status label/badge from the owning
     * enum, per-type kind wording, and both time renderings. Subjects are
     * always the session user's own (the feed is scoped), so the generated
     * links are safe; the fallbacks cover a future cascade gap.
     */
    private function enrich(LengthAwarePaginator $rows): void
    {
        $items = collect($rows->items());

        $websiteIds = $items->where('type', 'connection')->pluck('subject_id')->filter()->unique();
        $postIds = $items->where('type', '!=', 'connection')->pluck('subject_id')->filter()->unique();

        $websites = $websiteIds->isEmpty()
            ? collect()
            : Website::query()->whereIn('id', $websiteIds)->pluck('name', 'id');
        $posts = $postIds->isEmpty()
            ? collect()
            : BlogPost::query()->whereIn('id', $postIds)->pluck('title', 'id');

        foreach ($items as $row) {
            $row->kind_label = match ($row->type) {
                'connection' => ucwords(str_replace('_', ' ', (string) $row->kind)),
                'publishing' => 'Attempt '.(int) $row->kind,
                'ai' => match ((string) $row->kind) {
                    'openai' => 'OpenAI',
                    'development' => 'Development',
                    default => ucfirst((string) $row->kind),
                },
                default => (string) $row->kind,
            };

            if ($row->type === 'connection') {
                $row->subject_label = $websites[$row->subject_id] ?? 'Deleted website';
                $row->subject_url = isset($websites[$row->subject_id])
                    ? route('websites.show', $row->subject_id)
                    : null;
            } else {
                $row->subject_label = $posts[$row->subject_id] ?? 'Deleted post';
                $row->subject_url = isset($posts[$row->subject_id])
                    ? route('posts.show', $row->subject_id)
                    : null;
            }

            $presentation = $this->statusPresentation((string) $row->status);
            $row->status_label = $presentation['label'];
            $row->status_badge = $presentation['badge'];

            $when = Carbon::parse((string) $row->happened_at);
            $row->time_human = $when->diffForHumans();
            $row->time_exact = $when->format('M j, Y H:i:s');
            $row->detail = (string) ($row->detail ?? '');
        }
    }

    /**
     * Shared vocabulary across the three log enums: resolve the label and
     * badge variant from whichever enum owns the value.
     *
     * @return array{label: string, badge: string}
     */
    private function statusPresentation(string $status): array
    {
        foreach ([AiLogStatus::class, PublishingLogStatus::class, ConnectionLogStatus::class] as $enum) {
            $case = $enum::tryFrom($status);

            if ($case !== null) {
                return ['label' => $case->label(), 'badge' => $case->badge()];
            }
        }

        return ['label' => ucfirst($status), 'badge' => 'gray'];
    }
}
