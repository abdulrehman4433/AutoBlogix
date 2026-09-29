# Module 04 — Blog Posts (CRUD, filters, preview, statuses)

Status: complete

## 1. Purpose and place in the process

The authoring surface: create/edit/delete posts per website, list them with
filters, and preview content before it goes anywhere. Fills the **Posts** nav
item (the link already exists behind `Route::has('posts.index')`).

Phase boundaries:

- **This phase** owns the `draft ↔ scheduled` status pair driven by the
  `scheduled_at` field, plus storage of all post metadata.
- **Phase 5** adds "Publish now" / retry and owns `publishing/published/failed`.
- **Phase 6** creates `generating/generated` posts through the AI flow.
- **Phase 7's** scheduler consumes `scheduled_at` (stored in UTC) to run
  Phase 5's publishing job — nothing executes it in this phase.

## 2. Tables / columns / relationships touched

- `blog_posts` (full CRUD): `title`, `slug`, `topic`, `excerpt`, `content`,
  `category`, `tags` (array), `meta_description`, `keywords` (array),
  `scheduled_at`, plus system-owned columns never set from forms: `user_id`,
  `website_id` (validated), `status`, `source`, `published_at`,
  `wordpress_post_id`, `wordpress_url`, `failure_reason`,
  `publish_idempotency_key`, `ai_provider`, `ai_model`.
- `publishing_logs`: read-only display on the show page (rows appear in
  Phase 5).
- Relations: `User::blogPosts()`, `Website::blogPosts()`, `BlogPost::website()`,
  `BlogPost::publishingLogs()`.

## 3. Routes (web, inside `auth` + `verified`)

| Method | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/posts` | `posts.index` | PostsController@index (filters: `q`, `status`, `website`) |
| GET | `/posts/create` | `posts.create` | @create |
| POST | `/posts` | `posts.store` | @store |
| GET | `/posts/{post}` | `posts.show` | @show (preview + publishing history) |
| GET | `/posts/{post}/edit` | `posts.edit` | @edit |
| PUT/PATCH | `/posts/{post}` | `posts.update` | @update |
| DELETE | `/posts/{post}` | `posts.destroy` | @destroy |

`/posts/create` is registered **before** the resource wildcard (Phase 2 lesson).

## 4. Files to create / change

Create:

- `docs/modules/04-blog-posts.md` (this file)
- `app/Http/Requests/StorePostRequest.php` — shared by store **and** update
  (identical rules; intentional reuse — divergent update rules would be added
  as a subclass later)
- `app/Services/BlogPostService.php` — create/update/delete + slug + status
  transitions (thin controllers)
- `app/Policies/BlogPostPolicy.php` — owner-only (second layer behind the
  scoped route bind)
- `app/Support/HtmlSanitizer.php` — allowlist preview sanitizer (no new deps)
- `app/Http/Controllers/PostsController.php`
- `resources/views/posts/{index,create,edit,show}.blade.php` +
  shared `posts/partials/form.blade.php`
- `tests/Feature/PostsTest.php`

Change:

- `routes/web.php` (resource + create/store first)
- `app/Providers/AppServiceProvider.php` (owner-scoped `Route::bind('post')`)
- `PROGRESS.md`

## 5. Business rules / edge cases

- **Ownership:** `Route::bind('post')` resolves only the session user's posts
  → foreign IDs are **404**; `BlogPostPolicy` (view/update/delete = owner) as
  second layer. Website selection validated against the user's own websites.
- **Status on save:** if current status ∈ {draft, scheduled}: status becomes
  `scheduled` when `scheduled_at` is set, else `draft`. Any other status
  (publishing/published/failed/generating/generated/cancelled) is **never**
  changed by editing — publish outcomes belong to Phase 5, AI states to
  Phase 6.
- **Timezone:** `scheduled_at` input (datetime-local, no offset) is
  interpreted in the **website's timezone** and stored as UTC; views display
  it converted back to website time via model accessors. The Form Request
  keeps the raw local string (so a failed validation re-displays exactly what
  the user typed instead of a shifted UTC value) and
  `BlogPostService::scheduleToUtc()` converts after validation passes. The
  future-schedule closure rule parses in the website's timezone for an exact
  instant comparison against `now()`, and always accepts a value equal to the
  stored one so editing a post with an already-past `scheduled_at` doesn't
  get stuck.
- **Slug:** generated from title (fallback random), unique per website
  (suffix loop); local display only — WordPress assigns the final slug.
- **Tags/keywords:** comma-separated input → trimmed non-empty arrays
  (max 20 / 10 entries, max 50 chars each); rendered as badges/pills.
- **Content:** plain text or HTML for WordPress; the show page renders it
  through `HtmlSanitizer` (tag allowlist + `on*`/`javascript:` scrub) so AI-
  or user-provided markup can never execute in the UI. Flagged improvement:
  swap in a dedicated purifier package if rich-HTML needs grow.
- **Delete:** any post may be deleted (confirm modal); `publishing_logs`
  cascade at the DB level; a late Phase 5 job finding no row fails gracefully.
- **Filters:** invalid `status`/`website` query values are ignored (never
  422 on a GET); results paginate 15/page, `withQueryString()`, newest first.
- **Validation:** title ≤200, topic ≤200, excerpt ≤2000, content ≤500000,
  category ≤100, meta_description ≤300; unknown fields stripped; create page
  without any website shows an "add a website first" empty state.

## 6. UI pages and states

- **index:** header + "New post" CTA; filter bar (search, status select,
  website select, Apply/Reset); table (title→show, website, status badge,
  source badge, scheduled/published time in website tz, actions
  View/Edit/Delete with confirm modal); empty states (no posts /
  no websites → CTA to websites.create).
- **create/edit:** shared form partial — website select, title, topic,
  excerpt (textarea), content (textarea, hint about HTML), category, tags,
  keywords, meta description, scheduled-at (datetime-local, hint "website
  local time", only editable for draft/scheduled posts); validation errors
  inline; without websites → alert + link instead of the form.
- **show:** title + status/source badges + Edit/Delete actions; preview card
  (sanitized content, whitespace preserved, excerpt); meta sidebar (website,
  category, tags, keywords, meta description, scheduled/published times in
  website time, WordPress link when published, failure reason when failed);
  "Publishing history" table (empty state until Phase 5).

## 7. Acceptance criteria

- [x] All 7 routes registered; create/store before the `{post}` wildcard
- [x] Owner-scoped `Route::bind('post')` + `BlogPostPolicy`: another user's
      post → 404 on show/edit/update/delete; `Gate` denies too
- [x] Index lists only own posts; filters by status, website, and title/topic
      search work and persist in the query string; invalid filter values ignored
- [x] Store creates a draft (no schedule) or scheduled post (schedule set),
      with slug, `source=manual`, correct `user_id`/`website_id`
- [x] `scheduled_at` entered as website-local time is stored UTC and shown
      back in website time; past schedule rejected; unchanged past schedule
      still saves
- [x] Update transitions draft↔scheduled per rule and never touches
      publish/AI statuses; clearing the schedule returns scheduled→draft;
      tags/keywords round-trip as arrays
- [x] Show page renders sanitized preview (script/event handlers stripped)
      and shows meta + empty publishing history
- [x] Delete with confirm modal removes the post (and its logs)
- [x] Create page without websites shows the "add a website first" state
- [x] Full suite + Pint pass; HTTP smoke of list → create → show → edit →
      delete against the running dev server

## 8. Tests to be written (`tests/Feature/PostsTest.php`)

- guest → redirected to login
- index: only own posts; search/status/website filters; invalid filter values
  don't 422; empty state without posts; "add website" state without websites
- create page renders with a website; without websites → prompt state
- store: draft defaults (slug/status/source/ownership); schedule set →
  `scheduled` + UTC conversion from website tz; validation (missing title,
  foreign website, past schedule) → 422/redirect errors
- show: renders own post, strips `onerror`/`<script>` from preview, foreign
  post → 404
- update: fields + tags array; scheduled→draft on clearing schedule;
  published status untouched; unchanged past schedule accepted; new past
  schedule rejected; foreign post → 404
- delete: removes row (logs cascade); foreign post → 404
- policy: `Gate::forUser(other)->allows('update', $post)` is false

## As built (Phase 4)

**Files created**

- `docs/modules/04-blog-posts.md` (this file — written first)
- `app/Http/Requests/StorePostRequest.php` — shared by store and update
- `app/Services/BlogPostService.php` — create/update/delete, unique-per-site
  slug, `scheduleToUtc()`, draft↔scheduled status rule
- `app/Policies/BlogPostPolicy.php` — owner-only, auto-discovered
- `app/Support/HtmlSanitizer.php` — preview sanitizer (no new dependencies)
- `app/Http/Controllers/PostsController.php` — thin controller
- `resources/views/posts/{index,create,edit,show}.blade.php` +
  `resources/views/posts/partials/form.blade.php`
- `tests/Feature/PostsTest.php` — 16 tests / 95 assertions

**Files changed**

- `routes/web.php` — `/posts/create` + POST `/posts` before the resource
  wildcard; `App/Http/Controllers/PostsController` import
- `app/Providers/AppServiceProvider.php` — owner-scoped `Route::bind('post')`
- `app/Models/BlogPost.php` — `scheduledAtSiteTime()` / `publishedAtSiteTime()`

**Deviations from the original sketch**

- §5 originally said `prepareForValidation` converts `scheduled_at` to UTC.
  As built, the Form Request keeps the raw website-local string (so a failed
  validation re-displays exactly what the user typed instead of a shifted
  UTC value) and `BlogPostService::scheduleToUtc()` converts after validation
  passes; the future-schedule rule parses in the website's timezone for an
  exact instant comparison.
- The sanitizer test asserts payload-specific handlers (`steal()`,
  `alert(2)`) rather than the literal `onclick`, because the app layout's
  logout link legitimately uses `onclick=`.

**Verification (real output)**

- `vendor\bin\phpunit tests\Feature\PostsTest.php` → **OK (16 tests, 95 assertions)**
- full suite → **OK (128 tests, 484 assertions)** (112 from Phase 3 + 16 new)
- `vendor\bin\pint --dirty --format agent` → fixed 2 files (BlogPost,
  PostsTest — import ordering/FQCN); suite re-run green afterwards
- `npm run build` → ✓ built in 1.80s
- live HTTP smoke against `php artisan serve` → **22/22 steps passed**:
  login → create website (one-time secret banner) → posts create form →
  store (Draft badge, sanitized preview renders `<h2>`) → index + filters
  (status/search combo, invalid `status=garbage&website=999` ignored, no 422)
  → edit pre-fill → schedule `2030-06-15T09:30` on an America/New_York site
  shows "Jun 15, 2030 09:30 · website time" and DB row
  `scheduled | 2030-06-15 13:30:00` (UTC, EDT = UTC-4) → clearing the
  schedule returns to Draft → delete (flash + gone from index) →
  `/posts/99999` → 404 → cleanup; DB verified back to 0 rows in
  websites/blog_posts/connection_logs/publishing_logs.
