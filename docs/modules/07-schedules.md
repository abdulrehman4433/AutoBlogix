# Module 07 — Schedules (due publishing, stuck-attempt sweep, scheduler + queue runner)

Status: complete (Phase 7)

## 1. Purpose and place in the process

Makes scheduled publishing actually happen without a human clicking anything:

- The **Schedules** page lists every `scheduled` post (overdue first, then
  upcoming) with its time shown in the website's timezone, and offers
  **Run scheduler now** for environments without cron.
- The **scheduler** (Artisan command, every minute) publishes posts whose
  `scheduled_at` has arrived and recovers posts stuck in `publishing` after a
  stopped queue worker.
- The **queue runner** (`queue:work --stop-when-empty` every minute) drains the
  job queue so dispatched attempts complete.

Phase boundaries:

- **No new publishing logic.** The scheduler calls
  `PublishingService::requestPublish()` (Phase 5) — every idempotency rule
  already lives there; the sweep reuses the shared `applyOutcome()` finalizer.
- Only `PostStatus::Scheduled` fires. AI states (`generating`, `generated`),
  drafts, and terminal states are never candidates.
- The inbound plugin API (Phase 3) and posts CRUD (Phase 4) are untouched.

## 2. Tables / columns touched

No new tables — no migration in this phase.

- `blog_posts`:
  - read: `status = scheduled AND scheduled_at <= now()` (both UTC) — the due
    query; `status = publishing` — the sweep's candidate set.
  - write (sweep): `status → failed`, `failure_reason` = friendly timeout
    message (enables **Retry publish**).
- `publishing_logs`: the sweep closes the latest still-open row
  (`pending|processing → failed`, `error_message` = technical detail,
  `completed_at = now`); exactly one terminal row per attempt (Phase 5 rule).
- `jobs` (database queue): filled by `requestPublish()`, drained by the
  scheduled `queue:work`.

## 3. Routes

Web (inside `auth` + `verified`):

| Method | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/schedules` | `schedules.index` | SchedulesController@index |
| POST | `/schedules/run` | `schedules.run` | SchedulesController@run |

The index is **user-scoped** (own scheduled posts only). `run` is deliberately
**system-wide**: it processes every user's due posts, exactly like the cron
would — any signed-in user may trigger it (idempotent, DB-only, no outbound
HTTP inside `run` itself).

Console schedule (`routes/console.php`):

| Cadence | Command | Guards |
| --- | --- | --- |
| every minute | `posts:run-scheduler` | `withoutOverlapping(5)` |
| every minute | `queue:work --stop-when-empty` | `withoutOverlapping(5)` |

One cron line (`* * * * * php artisan schedule:run`, or
`php artisan schedule:work` in dev) therefore drives dispatch **and**
completion.

## 4. Files to create / change

Create:

- `docs/modules/07-schedules.md` (this file)
- `app/Console/Commands/RunScheduler.php` — `posts:run-scheduler`
- `app/Services/ScheduledPublishingService.php` — due query + orchestration
- `app/Http/Controllers/SchedulesController.php` — index + run
- `resources/views/schedules/index.blade.php`
- `tests/Feature/SchedulesTest.php`

Change:

- `app/Services/PublishingService.php` — add `sweepTimedOutPublishing()`
  (reuses the private `applyOutcome()`; needs the row-lock + re-check dance)
- `routes/web.php` — the two routes
- `routes/console.php` — the two `Schedule::command(...)` entries
- `PROGRESS.md`

## 5. Business rules / edge cases

- **Due query:** global across users (a scheduler is system-wide);
  `status = scheduled`, `scheduled_at` not null and `<= now()` (UTC column vs
  UTC app clock — no timezone math needed), ordered by `scheduled_at`.
  Posts missed while the server was down are simply overdue and publish on the
  next run.
- **One entry point:** each due post goes through `requestPublish()`; its
  result strings are tallied (`queued` / `already_publishing`+`published` /
  `not_publishable`) — never re-implemented, never an exception.
- **Sweep:** candidates are `status = publishing`; age basis is the latest
  **open** (`pending`/`processing`) log row's `started_at`, falling back to the
  post's `updated_at` when no open row exists (defensive: otherwise a log-less
  stuck post could never recover). Cutoff = `now − (WORDPRESS_API_TIMEOUT + 60 s
  grace)`; the grace keeps a genuinely in-flight attempt safe (the HTTP client
  gives up at the timeout, so nothing can still be running 60 s later).
  Re-check under `lockForUpdate`: status still `publishing` **and** the log row
  still open **and** still stale — then `applyOutcome(success: false, …)` via
  the shared finalizer: friendly `failure_reason` on the post, technical
  `error_message` in the log, `completed_at` set. Returns the recovery count;
  each recovery writes `Log::warning`.
- **Concurrency:** due-publish and sweep both take row locks; two simultaneous
  scheduler runs are safe (`already_publishing` no-ops; sweep re-checks).
- **Queue runner:** `--stop-when-empty` keeps the scheduled worker short-lived;
  `withoutOverlapping(5)` bounds stale mutexes (5-minute expiry — a crashed run
  cannot block the queue for hours); overlapping workers would be harmless
  anyway (database queue pops are atomic).
- **Timezone:** due comparison happens entirely in UTC; display converts with
  the existing `BlogPost::scheduledAtSiteTime()` (website tz — same rule as the
  Phase 4 form input).
- **No AI interference:** the sweep only looks at `publishing`; `generating`
  posts with interrupted `processing` AI logs stay Phase 6's recovery path.

## 6. UI pages and states

- Header: **Schedules** + one-line description; actions **Run scheduler now**
  (POST `schedules.run`) and **All posts**.
- Info alert explaining the runner setup: `schedule:run` (cron or
  `schedule:work`) fires the scheduler every minute; `queue:work` finishes the
  jobs; overdue posts publish on the next run after downtime.
- Warning alert when overdue count > 0: "*N* post(s) are overdue…".
- One table, sorted ascending (overdue naturally first): Title (link), Website
  (link), Publish at (website-time format + "site time" hint; overdue rows add
  an amber **Overdue** badge + relative time), actions **View** + **Publish now**
  (POST `posts.publish`, lands on the show page — Phase 5 behavior unchanged).
- Empty state: "Nothing scheduled yet." + **New post** CTA.
- Flash after **Run scheduler now**: `Scheduler ran: X due post(s), Y queued,
  Z stuck post(s) recovered.`
- The nav **Schedules** link (guarded by `Route::has('schedules.index')` since
  Phase 1) becomes visible.

## 7. Acceptance criteria

- [x] `schedules.index` + `schedules.run` registered; guest → login redirect;
      nav shows the Schedules link once the route exists
- [x] index lists only the session user's `scheduled` posts, sorted by time
      asc; overdue rows show the **Overdue** badge and website-time display;
      draft/generated/published/other-user posts are absent; empty state shown
      when nothing is scheduled
- [x] `POST /schedules/run` (sync + `Http::fake` success): due posts end
      `published` with fields set; future posts untouched; other users' due
      posts also publish (system-wide by design); flash counts are exact
- [x] non-`scheduled` statuses (draft, generated, failed, publishing,
      published) are never touched by a run
- [x] sweep: `publishing` + open log older than timeout+grace → post `failed`
      with friendly `failure_reason`, log `failed` with technical
      `error_message` + `completed_at`; a recent attempt and a terminal log
      row are left alone
- [x] `posts:run-scheduler` prints the summary line; `schedule:list` contains
      both `posts:run-scheduler` and `queue:work --stop-when-empty`
- [x] full suite + Pint pass; live smoke: schedule a post, run the real
      scheduler + worker, watch it publish

## 8. Tests to be written (`tests/Feature/SchedulesTest.php`)

- guest → redirected from index and run
- index: only my scheduled posts, order (overdue before upcoming), site-time
  string, **Overdue** badge; draft + other user's post absent; empty state
- run: due post publishes (Http::fake success), future untouched, flash
  message exact; other user's due post also publishes
- run: draft/generated/failed/publishing/published posts untouched
- sweep: stale pending log → recovered (status + failure_reason + log row +
  flash count); fresh processing attempt untouched; terminal log untouched
- command: `posts:run-scheduler` exits 0 with summary output
- schedule: `schedule:list` includes `posts:run-scheduler` and
  `queue:work --stop-when-empty`

---

## As built (Phase 7)

Everything above shipped as specified. Notable implementation notes:

- **No migration, no new table** — schedules are `blog_posts.scheduled_at`
  (UTC) exactly as designed in Phase 1; the phase is pure behavior + UI.
- **`ScheduledPublishingService`** owns the due query (global across users,
  `status = scheduled` + `scheduled_at <= now()`, deterministic
  `scheduled_at, id` ordering) and tallies `requestPublish()` outcomes;
  `run()` = `publishDue()` + the sweep. The sweep lives in
  `PublishingService` because it reuses the private `applyOutcome()` —
  friendly `failure_reason` and technical `error_message` land in their
  existing homes with zero duplicated finalization logic.
- **Sweep age basis:** latest **open** (`pending`/`processing`) log's
  `started_at`, falling back to the post's `updated_at` when no open row
  exists — a log-less zombie can still recover instead of being stuck
  forever. Re-checked under `lockForUpdate` inside a transaction (status
  still `publishing`, row still open, still past the cutoff) so a worker
  finishing concurrently never loses its outcome to the sweep.
  Terminal log rows are never overwritten (exactly-one-terminal rule from
  Phase 5); if one exists while the post is somehow still `publishing`, the
  post recovers and the log keeps its original terminal state.
- **Grace window:** `WORDPRESS_API_TIMEOUT (30) + 60 s` — the HTTP client
  gives up at the timeout, so nothing legitimate is still in flight when the
  cutoff passes; the constant is `SWEEP_GRACE_SECONDS`.
- **Command name:** `posts:run-scheduler` (deliberately not
  `scheduler:run` — too close to Laravel's `schedule:run`). Output:
  `Due: N (queued N, already handled N, skipped N); stuck recovered: N.`
- **Queue runner:** `queue:work --stop-when-empty` scheduled every minute
  alongside the scheduler, both `withoutOverlapping(5)` (short mutex expiry
  — a crashed run cannot block the loop for the default 24 h). One
  `schedule:run` cron line now drives dispatch **and** completion; dev uses
  `php artisan schedule:work`.
- **Manual "Run scheduler now"** calls the same service as the command —
  system-wide by design (any signed-in user triggers the tick the cron would
  run), idempotent, DB-only, no outbound HTTP inside `run` itself.
- **Flash/CLI strings are exact and tested:** `Scheduler ran: X due post(s),
  Y queued, Z stuck post(s) recovered.` / the `Due: …` line above.
- Timezone: due comparison is pure UTC-vs-UTC (column + app clock);
  display reuses `scheduledAtSiteTime()` (website tz, same as Phase 4).

### Verification (real output)

- Unit/feature: `SchedulesTest` → **OK (10 tests, 61 assertions)**;
  full suite → **OK (177 tests, 826 assertions)** (Phase 1–6 untouched).
- `vendor/bin/pint --dirty --format agent` → `{"result":"passed"}`.
- `npm run build` → built successfully.
- Live HTTP smoke (`phase7_smoke.ps1`, real dev server, real scheduler
  command, real queue worker, real NXDOMAIN) → **37 passed, 0 failed**:
  nav link → empty state → manual run flash (0/0/0) → `schedule:list` shows
  both entries → overdue post (badge + warning + "minutes ago") →
  `posts:run-scheduler` (`Due: 1 (queued 1 …)`, job row) → `queue:work --once`
  → friendly `Could not reach smoke-test.example.com…` → planted stuck
  attempt → sweep (`stuck recovered: 1`, friendly timeout reason vs
  technical `Swept:` in the log) → second run `Due: 0 … recovered: 0` →
  cleanup restored **0,0,0,0,0**.

### Handoff to Phase 8

- Phase 8 (Settings, logs UI, polish, final docs) gets `scheduling` as a
  final-doc topic (docs/scheduling.md): cron line for Linux, Task Scheduler
  for Windows, `schedule:work` for dev, and the `queue:work` requirement —
  the same facts the schedules page's info alert now shows.
- If a per-user "system" scoping is ever needed for `schedules.run`, scope
  the due query by `user_id`; today global matches cron semantics.
- No open TODOs from this phase. AI statuses were untouched (sweep only
  reads `publishing`); `generating` recovery remains Phase 6's path.
