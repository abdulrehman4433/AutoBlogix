# AutoBlogix — Build Process

How this application was (and is) built: a module-wise
**document-then-build** loop, one phase per commit, verified with real
output at every step.

## The loop

Each phase/module follows the same sequence:

1. **Document first** — create `docs/modules/NN-<module>.md` containing:
   purpose and phase boundaries · tables/columns touched · routes ·
   files to create/change · business rules and edge cases · UI pages and
   states · acceptance criteria (checkboxes) · tests to be written.
2. **Build** — implement exactly that doc: migrations/enum/model/factory →
   service → Form Request/policy → controller → routes → views → tests.
   Ambiguity is resolved with the *simplest Laravel-native option*, stated
   in one line, and continued; any larger improvement idea is flagged in one
   line rather than silently built.
3. **Verify with real output** — run the narrowest relevant tests, then the
   full suite (`vendor/bin/phpunit`), `vendor/bin/pint --dirty --format
   agent`, `npm run build`, and a live HTTP smoke script against the real
   dev server (login → drive the UI → assert DB rows → clean up back to the
   seeded `0,0,0,0,0` state). Claiming a pass without showing the command
   output is not allowed.
4. **Close the doc** — tick every acceptance checkbox and append an
   **As built** section (implementation notes, deviations, real
   verification output, handoff notes for the next phase).
5. **Update `PROGRESS.md`** — phase table, "what exists now", and a
   `Phase N+1 handoff` inside the open-issues entry.
6. **Commit once** — `Phase N: <name>` — then stop and wait for the next
   "continue".

## Phases

| # | Module | Doc | Status |
|---|--------|-----|--------|
| 1 | Project audit + auth + dashboard shell + seeding | `docs/modules/01-auth-dashboard.md` | ✅ |
| 2 | Websites + credentials + policies + connection logs | `docs/modules/02-websites.md` | ✅ |
| 3 | WordPress API (HMAC middleware, endpoints, rate limits) | `docs/modules/03-wordpress-api.md` | ✅ |
| 4 | Blog posts (CRUD, filters, preview, statuses) | `docs/modules/04-blog-posts.md` | ✅ |
| 5 | WordPress publishing (service, job, idempotency, retry) | `docs/modules/05-publishing.md` | ✅ |
| 6 | AI layer (providers, prompts, generation, logs) | `docs/modules/06-ai.md` | ✅ |
| 7 | Schedules + scheduler + queue runner + timezone logic | `docs/modules/07-schedules.md` | ✅ |
| 8 | Settings, activity logs, polish, final docs | `docs/modules/08-settings-logs.md` | ✅ |

(Old Phase 0 setup folded into Phase 1's doc: `docs/setup.md`.)

## Standing conventions

- **Stack:** Laravel 13 · PHP 8.4 (`declare(strict_types=1)`, PSR-12) ·
  Blade + Tailwind 4 + Alpine.js only · PHPUnit (not Pest) · MySQL 8 /
  MariaDB 12 · database queue + database cache + database sessions.
- **Structure:** thin controllers (validate → call service →
  redirect/render), services in `app/Services/` (no repositories), Form
  Requests for every write, one policy per resource, backed enums for
  statuses, factories for all models.
- **Tenancy:** owner-scoped `Route::bind()` (foreign IDs → **404**, never
  403) with policies as a second layer.
- **Status transitions** are owned by exactly one service each:
  `BlogPostService` (save rule: draft ↔ scheduled only),
  `PublishingService` (publishing lifecycle + sweep),
  `AiContentService` (generating/generated), `WordPressConnectionService`
  (connection state machine).
- **Errors:** users see friendly messages (`failure_reason`, flash text);
  technical detail goes to the log tables and `storage/logs` — never
  secrets, signatures, or raw exceptions in the UI.
- **Frontend build:** run `npm run build` after any Blade/CSS change.
- **Never commit `.env`.**

## Resuming work

Read [`PROGRESS.md`](../PROGRESS.md) first — it carries the phase table,
decisions, assumptions, and the latest phase's handoff notes. The module
docs' **As built** sections record exactly what shipped, including the real
test/smoke output for that phase.
