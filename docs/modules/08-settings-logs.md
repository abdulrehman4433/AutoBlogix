# Module 08 — Settings, activity logs, polish, final docs

Status: in progress (Phase 8)

## 1. Purpose and place in the process

Closes the MVP: the two remaining nav links become real pages, the whole
product gets its permanent documentation, and everything is verified one
last time end to end.

- **Settings** (`settings.index`) — account summary + link to the existing
  Breeze profile editor, the **AI prompt template editor** (the Phase 6
  handoff item: `prompt_templates` are global rows with a config fallback),
  and a read-only system card (queue/cache/timezone/environment provider).
- **Activity logs** (`logs.index`) — one user-scoped, cross-type feed over
  `connection_logs` + `publishing_logs` + `ai_logs`, with type/status filters
  (the per-entity detail tables on website/post show pages stay as they are).
- **Polish** — the `Route::has()`-guarded nav (Phase 1) finally lights up
  every item; route/console sanity, final consistency pass.
- **Final docs** — README, `docs/process.md`, `docs/architecture.md`,
  `docs/ai-providers.md`, `docs/scheduling.md` (the five deliverables named
  in the plan; `docs/setup.md` + `docs/wordpress-api.md` already exist).

Phase boundaries: no new business behavior — no new statuses, no migration,
no changes to AI/publishing/scheduling logic. Scope rules from the plan
still hold (no billing/teams/advanced SEO/image gen/social/analytics/bulk
generation), and the WordPress plugin remains out of this repository.

## 2. Tables / columns touched

No schema changes. Reads/writes:

- `prompt_templates` — `updateOrCreate` on `key = blog_post_generation`
  (Settings editor); `DELETE` row on reset (config fallback takes over).
  Global by design (documented on the page).
- `connection_logs`, `publishing_logs`, `ai_logs` — read-only feed, scoped:
  connection → `website_id IN (user's websites)`, publishing → `post_id IN
  (user's posts)`, ai → `user_id = session user`.

## 3. Routes (web, inside `auth` + `verified`)

| Method | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/settings` | `settings.index` | SettingsController@index |
| PATCH | `/settings/prompts` | `settings.prompts.update` | SettingsController@updatePrompts |
| DELETE | `/settings/prompts` | `settings.prompts.reset` | SettingsController@resetPrompts |
| GET | `/logs` | `logs.index` | LogsController@index |

No `{model}` binds (nothing to scope by ID — prompts are global, the feed is
session-scoped). No policy needed for the same reason.

## 4. Files to create / change

Create:

- `docs/modules/08-settings-logs.md` (this file)
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/LogsController.php`
- `app/Http/Requests/UpdatePromptTemplatesRequest.php`
- `resources/views/settings/index.blade.php`
- `resources/views/logs/index.blade.php`
- `tests/Feature/SettingsTest.php`
- `tests/Feature/LogsTest.php`
- `README.md` (rewrite — the current one is the Breeze skeleton)
- `docs/process.md`, `docs/architecture.md`, `docs/ai-providers.md`,
  `docs/scheduling.md`

Change:

- `routes/web.php` — the four routes
- `PROGRESS.md`

## 5. Business rules / edge cases

- **Prompt editor:** validation `required|string|max:12000` per field via a
  Form Request (422 → errors, never a 500). `updateOrCreate` keyed on
  `key = blog_post_generation`; the `name` falls back to
  `config('ai.defaults.blog_post_generation.name')` when creating. Reset
  deletes the row → `PromptTemplate::resolve()` transparently serves the
  config fallback again (generation is never left without a prompt).
  Placeholders (`:title`, `:topic`, `:keywords`, `:tone`, `:length_words`)
  are shown as documentation on the page, not validated — they are
  substituted at generation time (Phase 6).
- **Feed query:** three `UNION ALL` branches (only the selected types are
  built), each already user-scoped and status-filtered; outer query orders
  by `happened_at DESC, row_id DESC` and paginates (15/page,
  `withQueryString`). Branch columns: `type, subject_id, kind, status,
  detail, happened_at, row_id` — `kind` = action (connection) / attempt
  number (publishing) / provider (AI); `detail` = message / (response
  summary ?? error) / error. No `CONCAT` (sqlite tests + MySQL 8 must both
  run it — COALESCE only).
- **Subject names** resolved for the 15-row page in two extra queries
  (`whereIn` on websites + posts) — no N+1; deleted subjects (only possible
  through a future cascade gap) render as `Deleted …` rather than crashing.
- **Invalid filter values are ignored, never 422 on a GET** (Phase 4 rule,
  same implementation: `tryFrom` / in_array checks).
- **Status labels/badges reuse the enums:** `AiLogStatus` →
  `PublishingLogStatus` → `ConnectionLogStatus` `tryFrom` chain (shared
  vocabulary `success|failed|pending|processing`, identical labels).
- **Read-only feed:** no actions on log rows (lifecycle management lives on
  the entity pages: verify/rotate/publish/retry).
- **Settings info card** renders config only — never secrets (no API keys,
  no DB passwords).

## 6. UI pages and states

- **Settings:** header + three cards — Account (name, email, **Edit
  profile** → `profile.edit`), AI prompt template (system + user textareas,
  placeholder documentation, **Save template** + **Reset to default** with
  confirm, global-template note), System (app URL, timezone, queue, cache,
  environment AI provider). Flash on save/reset.
- **Logs:** header + filter row (Type: All/Connection/Publishing/AI ·
  Status: All/Success/Failed/Pending/Processing · Filter/Reset buttons) +
  feed table (Time · Type · Subject · Status · Detail) + pagination card +
  empty states (no logs at all vs no match after filtering).
- Nav shows **Logs** and **Settings** (last `Route::has` guards resolve).

## 7. Acceptance criteria

- [x] `settings.index` / `settings.prompts.update` / `settings.prompts.reset`
      / `logs.index` registered; guest → login redirect on all four
- [x] Settings renders account data, pre-filled prompt textareas (config
      fallback before any row exists), and the system card; profile link
      points at Breeze's `profile.edit`
- [x] Saving creates exactly one `prompt_templates` row (second save
      updates it, never duplicates); page + generation resolve the new
      text; validation rejects empty/oversized input without writing
- [x] Reset deletes the row and the page falls back to the config prompt
- [x] Logs feed: own rows across all three types with resolved subject
      names, kinds, and details; other users' rows invisible in every type
- [x] Type and status filters narrow correctly; invalid values are ignored;
      pagination works over the union; empty states render
- [x] Full suite + Pint + `npm run build` pass; live smoke exercises
      Settings (save/reset prompt) and Logs (filters) in the browser;
      nav activates every Phase 1 item
- [x] Final docs exist and are accurate: README, process, architecture,
      ai-providers, scheduling (plus the pre-existing setup +
      wordpress-api docs, cross-linked)

## 8. Tests to be written

`tests/Feature/SettingsTest.php`:

- guest → redirected from index/update/reset
- index: pre-fills from config fallback; shows account + system info
- update: creates one row; flash; second update edits the same row;
  `PromptTemplate::resolve()` returns the new text
- validation: missing field / oversized field → errors, no row written
- reset: row gone, fallback text back, flash

`tests/Feature/LogsTest.php`:

- guest → redirected
- default feed shows own connection + publishing + AI rows (subject names,
  `Attempt N`, action labels) and hides another user's rows in all types
- `?type=` narrows to one type; `?status=` narrows to one status
- invalid `type`/`status` values are ignored (200, unfiltered)
- pagination over the union (16+ rows → paginator renders)
- empty state with no logs

---

## As built (Phase 8 complete)

### Shipped

- `app/Http/Controllers/SettingsController.php` — `index` (account +
  `PromptTemplate::resolve()` + system card), `updatePrompts`
  (`updateOrCreate`, name preserved from row/config), `resetPrompts`
  (delete row → config fallback).
- `app/Http/Requests/UpdatePromptTemplatesRequest.php` —
  `required|string|max:12000` × 2 fields.
- `app/Http/Controllers/LogsController.php` — three-branch `UNION ALL`
  feed (only selected types built, each scoped + status-filtered),
  paginate 15 `withQueryString`, `enrich()` (2 batched subject lookups,
  kind/status/time rendering via the enum `tryFrom` chain),
  `statusPresentation()` fallback → gray badge for unknown values.
- `resources/views/settings/index.blade.php` (3 cards + reset-confirm
  modal), `resources/views/logs/index.blade.php` (filter bar, 5-column
  feed, pagination card, dual empty states).
- `routes/web.php` — the 4 routes inside `auth`+`verified`; no binds, no
  policy (nothing per-ID: prompts are global, feed is session-scoped).
- `tests/Feature/SettingsTest.php` (5), `tests/Feature/LogsTest.php` (7).
- Final docs: `README.md` (rewritten), `docs/process.md`,
  `docs/architecture.md`, `docs/ai-providers.md`, `docs/scheduling.md`.

### Deviations / notes

- No material deviation from §5. Small additions: the System card also
  shows Laravel/PHP versions; the reset modal is inline with a fixed
  route URL (no dynamic Alpine state needed, unlike posts' delete modal).
- Publishing rows use the raw `attempt` int as `kind` (rendered
  `Attempt N` in the view) — deliberately avoiding `CONCAT()` so the same
  SQL runs on sqlite (tests) and MariaDB/MySQL.
- **Smoke gotcha:** creating a website through the form auto-writes one
  credential-lifecycle connection log (`WebsiteCredentialService::log`),
  so raw totals exceed planted rows — the smoke counts `SMOKE`-tagged
  messages instead.

### Verification (real output)

New tests — SettingsTest + LogsTest:

```
OK (12 tests, 97 assertions)
```

Full suite (after Pint's import fix to LogsController):

```
PHPUnit 12.5.36 ... 189 / 189 (100%)
OK (189 tests, 923 assertions)
```

Pint + build:

```
vendor\bin\pint --dirty --format agent
→ fixed: LogsController.php (fully_qualified_strict_types, ordered_imports) — clean afterwards

npm run build → ✓ built in 1.69s (app-DIZEten-.js 54.33 kB, app-DNfw82Ht.css 42.64 kB)
```

Routes:

```
GET     settings            → settings.index
PATCH   settings/prompts    → settings.prompts.update
DELETE  settings/prompts    → settings.prompts.reset
GET     logs                → logs.index
```

Live smoke (`phase8_smoke.ps1`, dev server 127.0.0.1:8000):

```
=== RESULT: 46 passed, 0 failed ===
```

covering: nav hrefs + labels for Logs/Settings active (last `Route::has`
guards) → settings cards + config-fallback pre-fill (prompt_templates=0)
→ save flash + exactly 1 row + page shows saved text → reset flash + 0
rows + fallback back → logs empty state → website/post fixtures → feed
shows all three types with subjects (`Phase 8 Smoke`, `Phase 8 Smoke
Post`), kinds (`Connect`, `Attempt 1`, `Development`), details, and
Success/Failed badges → `?type=` × 3, `?status=failed|success`, invalid
values ignored (200) → cleanup restored **0,0,0,0,0,0,0** (websites,
posts, connection_logs, publishing_logs, ai_logs, prompt_templates, jobs).

### Handoff

None — **all plan phases (1–8) are complete**. Post-MVP items remain
deferred by the plan (billing, teams, advanced SEO, image generation,
social, analytics, bulk generation); the WordPress plugin is a separate
project (`docs/wordpress-api.md` is its contract). `PROGRESS.md` is the
resume point.
