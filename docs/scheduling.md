# AutoBlogix — Scheduling & the Publish Loop

Everything time-driven in AutoBlogix hangs off **one cron line**. This doc
mirrors the info alert on the Schedules page and explains the sweep rules.

## What runs, every minute

Defined in `routes/console.php`:

| Schedule entry | What it does |
| --- | --- |
| `posts:run-scheduler` | Publishes due posts (`status = scheduled` and `scheduled_at <= now`, stored UTC) via `PublishingService::requestPublish()`, then sweeps attempts stranded by a stopped worker (`PublishingService::sweepTimedOutPublishing()`). |
| `queue:work --stop-when-empty` | Executes the `PublishPostJob`s the scheduler dispatched, then exits (cron re-invokes it next minute). |

Both are `->everyMinute()->withoutOverlapping(5)` — a crashed run cannot
block the loop for hours (stale mutexes expire after 5 minutes).

So one `schedule:run` tick does **dispatch AND completion**; you never run
`queue:work` permanently in production. The queue worker still has to be
*willing* to run — if the queue is full of jobs but nothing drains them,
posts sit in `Publishing…` until the sweep times them out.

## Production (cron)

```cron
* * * * * cd /path/to/autoblogix && php artisan schedule:run >> /dev/null 2>&1
```

Install with `crontab -e` (Linux) or the web host's cron UI. Verify with:

```sh
php artisan schedule:list
# posts:run-scheduler   ... every minute
# queue:work --stop-when-empty   ... every minute
```

## Windows (Task Scheduler)

1. Open **Task Scheduler** → *Create Basic Task*.
2. Name: `AutoBlogix scheduler`; Trigger: **Daily**, then check
   *Repeat task every: 1 minute* (indefinitely).
3. Action: **Start a program** →
   - Program: `C:\zampp\php\php.exe` (your PHP binary)
   - Arguments: `artisan schedule:run`
   - *Start in*: `C:\path\to\autoblogix` (the project root — required)
4. Finish, then run the task once manually and confirm
   `php artisan schedule:list` behaves as expected.

## Development

```sh
php artisan schedule:work     # runs the scheduler loop in the foreground (every minute)
```

`schedule:work` replaces cron locally — it fires both entries each minute.
If you only want a single manual tick (no loop):

```sh
php artisan schedule:run
# or just the scheduler part, without waiting for cron:
php artisan posts:run-scheduler
```

The Schedules page (**Schedules → Run scheduler now**, `POST /schedules/run`)
does the same from the UI: it is system-wide by design (a manual cron tick),
idempotent, database-only, so it is safe to press as often as you like.

## The queue worker requirement

Publishing jobs run on the **database queue**. During a single
`php artisan serve` session you also need:

```sh
php artisan queue:work
```

Symptoms when it is missing:

- Post stays **`Publishing…`** on the show page (the job sits in the
  `jobs` table), until…
- …the **sweep** (next scheduler run after `WORDPRESS_API_TIMEOUT + 60s`)
  marks it `failed` with the friendly reason:
  *"Publishing timed out after 30 seconds — the queue worker may not be
  running. Start it (`php artisan queue:work`) and use Retry publish."*
  The technical sweep detail goes into `publishing_logs.error_message`
  (prefixed `Swept:`); a `success`/`failed` log is **never** overwritten.

With `schedule:work` running, the scheduled `queue:work --stop-when-empty`
entry drains the queue for you — no separate worker terminal needed.

## Sweep rules (stuck-attempt recovery)

- Reads only posts in `publishing` — AI statuses are never touched.
- Age = latest **open** log's `started_at` (fallback: post `updated_at`).
- Cutoff = now − (`WORDPRESS_API_TIMEOUT` + `SWEEP_GRACE_SECONDS` = 30 + 60 s).
- Re-checks status under `lockForUpdate` before acting (races with a live
  attempt are harmless: the winner's outcome stands).

## Timezone rules

- `scheduled_at` is stored **UTC** (`BlogPostService::scheduleToUtc()`), so
  due comparisons are UTC-vs-UTC and immune to server locale.
- Input and display use the **website's timezone** (per-site `timezone`
  column): the form shows site-local labels, the Schedules page shows
  `M j, Y H:i` in site time with the relative time beneath, and the
  *Overdue* badge compares in absolute terms.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Due posts not publishing | Cron/`schedule:work` not running | Add the cron line or start `schedule:work`; check `php artisan schedule:list` |
| Post stuck in `Publishing…` | Queue worker not draining | `php artisan queue:work` (or wait for the sweep to fail it and retry) |
| `Swept: …` in a log, friendly timeout on the post | Worker was down past timeout + grace | Start the worker, use **Retry publish** — the idempotency key prevents duplicates |
| Overdue warning on Schedules page | Scheduler hasn't ticked since the time passed | Runs on the next minute's tick; or press **Run scheduler now** |
| Jobs pile up in `jobs` table | `queue:work --stop-when-empty` entry missing | Run `php artisan schedule:list`; re-run migrations/cache config if entries vanished |
