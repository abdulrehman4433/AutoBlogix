# AutoBlogix — UI Design System

The glossy SaaS look of AutoBlogix is **Blade + Tailwind CSS v4 + Alpine.js**
only — no component framework, no JS build beyond Vite, no icon package. This
doc is the reference for everything visual: tokens, component classes, Blade
components, and the rules that keep the UI consistent (and the 189 feature
tests green).

## Where things live

| Path | Contains |
| --- | --- |
| `resources/css/app.css` | Design tokens (`@theme`), runtime dark-ready vars, base layer, component layer (`.btn`, `.card`, …), motion helpers, reduced-motion guard |
| `resources/views/components/` | The custom Blade component set (restyled in place) + the Breeze components (same API, restyled) |
| `resources/views/layouts/` | `app` (auth shell), `guest` (auth pages), `navigation` (sidebar menu) |
| `resources/views/vendor/pagination/tailwind.blade.php` | Custom pagination look |
| `vite.config.js` | Self-hosted Inter via `laravel-vite-plugin/fonts` (`bunny('Inter', [400,500,600,700,800])`) |
| `resources/js/app.js` | Alpine.js only (`import Alpine …; Alpine.start()`) |

Build: `npm run dev` (watch) or `npm run build` → self-hosted woff2 + fonts CSS
+ `app.css` (~56 kB, ~11 kB gzip).

## Design tokens (`@theme` in `app.css`)

Tailwind v4 is CSS-first: there is **no `tailwind.config.js`**. Every token
below is a CSS variable in `@theme`, so using e.g. `bg-brand-600` anywhere
generates exactly one utility from that token.

| Group | Tokens |
| --- | --- |
| Typography | `--font-sans` → **Inter** (self-hosted, weights 400/500/600/700/800) |
| Brand (indigo→violet) | `brand-50 … brand-950` (`bg-brand-600`, `text-brand-700`, …) |
| Accent (cyan) | `accent`, `accent-soft` |
| Semantic (AA pairs) | `success`/`warning`/`danger`/`info`, each with `-strong` (text on white) and `-soft` (background) |
| Surfaces & ink | `canvas #f8fafc`, `surface #fff`, `surface-muted`, `ink`, `ink-muted`, `ink-faint`, `line` |
| Gradients | `--gradient-brand` (indigo→violet), `--gradient-accent` (cyan), `--gradient-danger` — surfaced as utilities `.bg-brand-gradient`, `.bg-accent-gradient` |
| Shadows | `shadow-xs/sm/md/lg` (soft, layered), `shadow-glow` (indigo focus glow) |
| Radius | `radius-lg 10px`, `radius-xl 14px`, `radius-2xl 18px` |
| Motion | `--ease-standard`, `--ease-spring`; animations `fade-up` (450 ms), `fade-in` (200 ms), `shimmer`, `pulse-dot` |

**Runtime variables (`:root` / `.dark`, prefix `--abx-*`)** — the component
layer reads these (`--abx-surface`, `--abx-line`, `--abx-ink`, `--abx-hi`,
glass values…). They are the theme-switch point: everything is light by
default; adding `class="dark"` to `<html>` flips the whole component layer
(`@custom-variant dark` powers `dark:` utilities too). Dark is *ready*,
not yet enabled.

## Component classes (`@layer components`)

Always shipped (never tree-shaken), so they are safe to use directly in Blade:

| Class | Purpose |
| --- | --- |
| `.card`, `.card-hover` | White panel: border, radius-xl, layered shadow + inner top highlight (add your own `p-6`) |
| `.glass` | Translucent blurred panel (topbar/drawer style) |
| `.bg-brand-gradient`, `.bg-accent-gradient` | Glossy gradient fills (buttons, active pills, avatars) |
| `.btn` + `.btn-primary/.btn-secondary/.btn-ghost/.btn-danger` + `.btn-sm/.btn-md/.btn-lg` | Full button system (glossy sheen via `::after`, hover lift, focus ring, disabled state) |
| `.input`, `.input:focus`, `.input:disabled` | Text inputs/selects/textareas |
| `.input-hint`, `.input-message`, `.input-message--error/--success` | Helper + validation text |
| `.field`, `.field--float` | Label wrapper; `--float` = floating-label behavior (sibling selectors, no `:has()`) |
| `.check`, `.toggle` (+ `.toggle-input` sr-only checkbox) | Custom checkbox and iOS-style switch |
| `.badge` + `.badge-neutral/.badge-success/.badge-warning/.badge-danger/.badge-info/.badge-brand` | Status pills (optional `.badge-plain` = no dot) |
| `.table` / `.abx-table`, `.table thead th` …, `.table-zebra`, `.table-wrap`, `.table-scroll` | Table system (uppercase headers, row hover, zebra option, horizontal scroll wrapper) |
| `.skeleton`, `.skeleton-text` | Shimmer loading placeholders |
| `.progress`, `.progress-bar` | Progress bar (used by `x-progress`) |
| `.tooltip`, `.toast`, `.link` | Styled tooltip/toast/text link |
| `.fade-up`, `.stagger > *` | Page-load motion (see rules below) |

Base layer: `body` uses `--abx-canvas`/`--abx-ink`, `::selection` is
brand-tinted, links get a brand `:focus-visible` outline. A global
**`prefers-reduced-motion` guard** collapses all animation/transition durations.

## Blade components

### Custom set (`resources/views/components/`)

| Component | Props (defaults) | Notes |
| --- | --- | --- |
| `x-button` | `variant=primary\|secondary\|danger\|ghost`, `size=sm\|md\|lg`, `type=button`, `href`, `disabled`, `loading` | `href` renders an `<a>`; `loading` shows a spinner and disables |
| `x-copy-button` | `value` (required), `label=Copy`, `copiedLabel=Copied!`, `size=sm`, `variant=secondary` | Copies `value` to the clipboard on click and shows an animated **"Copied!" tooltip** above the button (1.5 s, `role="status"`). The value rides in a Blade-escaped `data-copy` attribute — **never put `@js()` inside a component attribute, it does not compile** (it ships literal and the handler dies) |
| `x-input` | `name`, `label`, `type=text`, `required`, `disabled`, `placeholder`, `hint`, `autocomplete`, `autofocus`, `value` | **Floating label only when `placeholder` is omitted.** Errors come from `$errors->first($name)` automatically; `aria-describedby`/`aria-invalid` wired |
| `x-select` | `name`, `label`, `options=[value=>label]`, `selected`, `placeholder` (empty first option), `required`, `disabled`, `hint` | Static label (never floats) |
| `x-textarea` | `name`, `label`, `rows=6`, `placeholder`, `hint`, `required`, `disabled`, `value` | Same floating/label/error behavior as `x-input` |
| `x-alert` | `type=info\|success\|warning\|error`, `title`, `dismissible` | Soft-tinted banner, `role="alert"`, Alpine fade |
| `x-badge` | `variant=gray\|green\|red\|amber\|blue\|indigo`, `dot=true` | Maps to semantic badges: gray→neutral, green→success, red→danger, amber→warning, blue→info, indigo→brand. **Enums' `badge()` returns these legacy keys on purpose — don't change either side alone.** `:dot="false"` hides the status dot |
| `x-table` | `columns=1`, `isEmpty=false` + slots `head`, `body`, `empty` | Renders `.table-wrap > table.table`; empty slot shown in a col-span row |
| `x-modal` | `name`, `title`, `maxWidth=sm\|md\|lg\|xl`, `show=false` (render open on load), `focusable` (auto-focus first field when open) + slot `footer` | Open with `$dispatch('open-modal', 'name')`, close with `'close-modal'`; Escape/backdrop click close; blurred backdrop. **Every delete/remove action in the app goes through this modal** (confirm submits the nested DELETE form, Cancel closes). The open handler must read **`$event.detail`** — Alpine event expressions only expose `$event`; a bare `detail` throws `ReferenceError: detail is not defined` and the modal silently never opens |
| `x-icon` | `name`, `size=xs\|sm\|md\|lg\|xl` (3.5/4/5/6/8), `strokeWidth=1.5` | Inline SVG registry: home, globe, document-text, calendar, sparkles, desktop, list-bullet, cog, chart-bar, server, plus, x-mark, check, check-circle, exclamation-triangle, exclamation-circle, information-circle, chevrons, arrows, arrow-path, clock, pencil-square, trash, eye, bars-3, magnifying-glass, user, envelope, key, bolt, link, credit-card. **Add new icons to the registry, don't inline paths** |
| `x-stat-card` | `label`, `value` (or slot), `icon`, `tone=brand\|gradient\|success\|warning\|danger\|info\|neutral`, `hint` | Dashboard metric card |
| `x-toast` | `type=success\|error\|warning\|info`, `message`, `duration=3000` (ms, min 1000) | Fixed **top-right**, auto-dismisses after 3 s, `role="status"`. **Solid opaque background** (`.toast` — no glass/backdrop blur) and it **slides up into place from below** (translate+fade both ways). Root merges `$attributes` so callers can reposition. The app layout renders it for `session('success')`, `session('error')`, **and `$errors->any()`** (first validation message) — so every save shows top-right feedback |
| `x-empty-state` | `title`, `description`, `icon=document-text`, `compact` + slot `actions` | Centered empty panel for tables/lists/CTAs |
| `x-skeleton` | `variant=text\|circle\|title\|block`, `lines=1`, `width`, `height` | Loading placeholders |
| `x-tabs` | `tabs=['key'=>'Label']`, `default` | Alpine state `tab` shared with panels via inheritance |
| `x-tab-panel` | `name=key` | Pair of a tab; slotted inside `x-tabs` |
| `x-toggle` | `name`, `checked`, `disabled`, `label` | Switch backed by a real checkbox input |
| `x-progress` | `value=0`, `max=100`, `label`, `showValue` | `.progress` bar with ARIA `progressbar` |

### Breeze set (kept, restyled — same APIs as stock Breeze)

`x-text-input`, `x-input-label`, `x-input-error`, `x-primary-button`,
`x-secondary-button`, `x-danger-button`, `x-nav-link`, `x-responsive-nav-link`,
`x-dropdown` (+ `trigger`/`content` slots), `x-dropdown-link`,
`x-auth-session-status`, `x-application-logo`. Both component systems coexist
deliberately: new screens use the custom set; auth/profile keep Breeze's. No
call-site migration churn.

## Layouts

- **`layouts/app`** — dark glass sidebar (`bg-gray-900/90 backdrop-blur-xl`,
  gradient logo chip), mobile drawer (Alpine `mobileOpen`,
  `@close-navigation.window`), blurred sticky topbar
  (`bg-white/80 backdrop-blur-xl`), `$title` (topbar h1) and `$header`
  (below-title row) slots. **Save feedback renders as `x-toast` at the top
  right** — `session('success')`, `session('error')`, and validation failures
  (`$errors->first()`), all auto-dismissing after 3 seconds (still plain DOM
  text for tests).
- **`layouts/navigation`** — item array with `x-icon` names; items render only
  when `Route::has(...)`; active item = brand-gradient pill with `shadow-glow`,
  `aria-current="page"`.
- **`layouts/guest`** — auth pages: decorative brand/accent glow orbs, gradient
  logo chip, glass `.card` with `fade-up`.

## Pagination

`{{ $paginator->links('vendor.pagination.tailwind') }}` renders
`resources/views/vendor/pagination/tailwind.blade.php`: gradient active page,
bordered icon arrows (`x-icon`), "Showing X to Y of Z results" summary,
mobile Previous/Next row. It reuses the stock translated strings
(`pagination.previous/next`, `Showing`, `to`, `of`, `results`,
`Go to page :page`) — **keep them**; `LogsTest` asserts `Next`.

## Rules (read before styling anything)

1. **Copy, routes and field names are frozen** — 189 feature tests assert
   exact strings (`assertSee`). Visual restyling only: classes, markup shape,
   components. Never rename a form `name`, route, or user-facing text.
2. **Motion = transform/opacity only, short (150–450 ms), eased** — and a
   global `prefers-reduced-motion` guard already neutralizes it.
3. **Never put `.fade-up` / `.stagger` on an ancestor of `x-modal`.** The
   persistent `transform` creates a containing block and breaks the modal's
   `fixed` overlay. Page wrappers that contain modals use **`animate-fade-in`
   (opacity-only, safe)**; use `.fade-up`/`.stagger` only on modal-free
   subtrees (e.g. the dashboard metric grid).
4. **Floating labels only when a field has no `placeholder`** — the sibling-
   selector CSS keys off `:placeholder-shown`. Passing both keeps a static
   label (that's the intended fallback, used by `x-select` and Breeze forms).
5. **Mobile-first, 360–1536 px, ≥44 px touch targets** — interactive rows use
   `py-2`+ with pill buttons; tables scroll horizontally via `.table-wrap`
   instead of shrinking type.
6. **Icons come from the `x-icon` registry** (Heroicons-style 24px grid,
   stroke). No icon font, no CDN, no inline path strings outside the registry.
7. **Dark mode**: component layer reads `--abx-*` vars — enable later by
   adding `class="dark"` to `<html>`; no component rewrite needed. `dark:`
   utilities are available via `@custom-variant`.
8. **Alpine elements start `x-cloak`'d** so nothing flashes unstyled.
9. **Tokens over one-off values** — prefer `text-ink-muted` over `text-gray-500`,
   `border-line` over `border-gray-200`, `bg-surface-muted` over `bg-gray-50`,
   `text-brand-600` over `text-indigo-600`. Hard-coded Tailwind grays belong
   only on the dark-glass sidebar/drawer.

## Verification

```sh
npm run build                        # clean Vite build (fonts self-hosted)
php artisan test --compact           # 189 passed, 923 assertions
vendor/bin/pint --dirty --format agent   # formatting after PHP/Blade edits
```

The test suite is the render check: every page, component and flash path is
exercised by feature tests (components additionally by render smoke passes
during development). After CSS changes, confirm new utilities exist in
`public/build/assets/app-*.css` — Tailwind v4 only emits what it sees
scanned, and `vendor/` (pagination views) is git-ignored, which is exactly why
pagination has a first-party view in `resources/views/vendor/`.
