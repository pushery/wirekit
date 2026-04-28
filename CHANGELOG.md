# Changelog

All notable changes to WireKit are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

Post-1.3.0 maintenance pass. No breaking changes — every entry is fully backward-compatible with 1.3.0.x.

### Added

- **Tier 2 component-preview accessibility coverage.** New `sample/tests/Browser/ComponentPreviewCoverageTest.php` runs a parametric axe-core scan over every `:::preview` block in `docs/components/**` — ~520 blocks across the 109 component pages. Tagged `--group=full-a11y` and `--group=components-a11y`, on-demand only (NOT in the PR-blocking smoke run). Closes the structural gap that previously left `docs/components/**` previews uncovered by Tier 2 (only `docs/layouts/**` + `docs/blueprints/**` had parametric coverage). Combined with the existing `LayoutPreviewCoverageTest` and `BlueprintPreviewCoverageTest`, the on-demand `--group=full-a11y` run now exercises every preview block across all three sections — the full ~620-block sweep takes about 40 min on cold start.

- **`<x-wirekit::action-bar mode="static">`** — second layout mode for the action bar. The default `mode="floating"` keeps the existing `position: fixed` + viewport-centring transforms (fully back-compat). The new `mode="static"` flows inline with surrounding content (drops the fixed positioning + the centring transforms; keeps the same chrome — border, shadow, padding, rounded corners). Useful when the bar is part of a card / panel / dashboard rather than a viewport-floating overlay.

- **`<x-wirekit::toast-region eventScope="…">`** — optional CSS selector that scopes incoming toast events by DOM containment. When set, only events whose dispatching element is inside an ancestor matching the selector are handled. Useful for "per-section toast surfaces" where multiple toast regions on the same page must not cross-talk. The existing `name` parameter (event-name routing) is unchanged and still works in parallel; `eventScope` is additive. Default `null` preserves the global-listener behaviour.

- **Optional `related:` frontmatter array on docs pages** — list of route-relative paths (`/components/badge`, `/recipes/foo`, `/blueprints/...`) consumed by the docs site's "Copy for LLM → Compact summary" feature, which expands them into a `Related:` block at the top of the LLM-fed summary text. Cap of 5 entries per page, ordered by relevance, every path must point to a real published docs page. Seeded as a starting set on the Card, Button, Callout, and Feature-numbered-marker pages.

- **`SandboxRenderer::BODY_WRAPPERS` map** — auto-wraps the sandbox body slot in a sub-component for primitives whose composition requires it. The Card schema now wraps its body in `<x-wirekit::card.body>` so the live sandbox preview renders the full card chrome with padded body content instead of a bare rounded pill. Open extension point: future multi-slot primitives (tabs, accordion, …) can opt in by adding an entry.

- **Prop-level anti-drift test for sandbox schemas** — every prop registered in `SandboxSchemaRegistry` must map to a real `@props([...])` entry on the corresponding component (except `body`, the renderer's reserved slot-content key). Catches schemas that declare props the component never read — those previously emitted as raw HTML attributes (silently no-op), producing broken live previews. Fails the build on any drift; runs with every Pest invocation.

### Fixed

- **Resizable handle drag no longer flickers from text-selection in adjacent panels.** The handle's `pointerdown` already called `setPointerCapture()`, but the body `user-select` was never disabled during drag — so the cursor crossing a sibling panel highlighted that text and the browser repainted the selection mid-drag (visible as a "flicker"). `onPointerDown` now captures the prior inline value of `body.style.userSelect` and sets it to `'none'`; `onPointerUp` restores it. `pointercancel` already routes through `onPointerUp` so a tab-switch mid-drag also cleans up — no leak.

- **Vertical `<x-wirekit::resizable>` no longer collapses to 0 height when the wrapper has no explicit size.** `[data-wk-resizable][data-wk-direction="vertical"]` carries `contain: size`, which requires an explicit container size — without one, the panels' percent heights resolve against a 0-height container and the whole component disappears. Added a default `min-height: 16rem` on the vertical wrapper so unstyled containers still render visibly. Authors with explicit inline `style="min-height: …"` keep their value (CSS specificity favours the inline rule).

- **`docs/components/icon.md` install code-block: comment columns now line up.** The `composer require` lines for the four icon-set packages had hand-tuned spacing that drifted by 1–2 chars per line — every `#` comment marker landed at a different column (54 / 55 / 56 / 56), rendering visibly staggered even in a true monospace font. Re-aligned every `#` to column 56.

- **WCAG 4.1.2 (Name, Role, Value) sweep across 11 interactive components.** The new Tier 2 component-preview a11y coverage caught a class of issues where Alpine's reactive `:aria-*` bindings only emit the attribute after the JS boots — initial server-rendered HTML lacked the required `aria-expanded` (combobox, multi-select), `aria-valuenow` (image-compare, range-slider), or `aria-checked` (rating, segmented-control). Each affected component now ships a static `aria-foo="default-value"` immediately before the `:aria-foo="reactive-binding"` so server-rendered HTML is WCAG-complete from the first paint and Alpine overrides reactively after hydration. Plus: `<x-wirekit::brand>` in logo-only mode auto-injects `aria-label="Home"` (caller's `aria-label` still wins); `<x-wirekit::toast-region>` adds `role="region"` so its `aria-label="Notifications"` is permitted; `<x-wirekit::date-picker>`, `<x-wirekit::color-picker>`, `<x-wirekit::slider>` accept a new `label` prop and fall back to a sr-only label derived from `name` when no label / `aria-label` / `aria-labelledby` is provided; `<x-wirekit::navigation-menu.item>` link-mode now falls back to the default slot when no `trigger` prop is set so the canonical `<x-wirekit::navigation-menu.item href="/x">Label</x-wirekit::navigation-menu.item>` pattern produces a non-empty link.

- **Decorative inline SVGs in 5 docs previews now carry `aria-hidden="true"`.** The `just _audit` browser-based alt-tag audit caught a "branded QR-code with logo overlay" preview on `/components/qr-code` whose lightning-bolt overlay SVG was a bare element with no accessibility attributes — the icon is purely decorative (semantic meaning conveyed by the QR code itself), so the SVG must be hidden from assistive tech. A defence-in-depth scan across all 228 docs files surfaced four more decorative SVGs in the same shape (carousel slide illustrations × 3, empty-state icon-slot example, project-management mini-chart visualization), all now carry `aria-hidden="true"`. Verified via re-run of the audit script with proper code-block stripping — zero bare SVGs remain in any rendered preview.

- **Card live sandbox preview rendered as a bare rounded pill instead of a proper card.** The Card sandbox schema declared `padded` / `bordered` / `elevated` boolean props that the Card primitive never read in its `@props([...])` block — the renderer emitted them as raw HTML attributes (silently no-op), and the body slot was never wrapped in `<x-wirekit::card.body>`, so slot text pressed flush against the rounded card edges. Schema rewritten to use the real `variant` prop (`outlined` / `elevated` / `flat`); body slot now auto-wraps via the new `BODY_WRAPPERS` map. Three render-output regression tests added.

- **`code-block` sandbox schema declared `lang`, but the actual prop is `language`.** The renderer emitted `<x-wirekit::code-block lang="php">` which the component ignored — the syntax-highlight language class was never applied. Renamed the schema key to match the component contract.

- **`text` sandbox schema declared a `muted` boolean.** The Text primitive has no `muted` prop; it uses a string `variant` enum with allowed values `default` / `muted` / `subtle` / `accent` / `success` / `warning` / `danger`. Replaced the boolean with the real `variant` enum.

- **Callout sandbox schema dropped the un-renderable `title` reference.** `title` is a named slot in the Callout primitive (`@isset($title)`), not a `@props` entry, so the renderer's string-prop-as-HTML-attribute path could never populate it. Schema now carries only the props the renderer can actually deliver. (`alert` keeps `title` because Alert.title IS a real `@props` entry — different shape.)

- **`button` and `badge` sandbox schemas: slot key `label` → `body`.** Earlier iterations declared `label` as the slot-content key, which the renderer treated as an HTML attribute and never inserted into the slot — live previews rendered empty buttons / badges. Renamed to the renderer's reserved `body` convention.

- **`clipboard-button` button-width regression on state change.** The first iteration of the stable-width fix used `x-show` to toggle which label rendered inside the grid cell. `x-show` sets `display: none` on the inactive label, removing it from layout entirely; the grid cell then collapsed to whichever child was visible — defeating the stable-width goal. Replaced with `:style="{ visibility: ... }"` toggling so both labels stay in layout permanently and the grid cell sizes to the wider one. The copied-state span also carries a static `style="visibility: hidden"` so its layout slot is reserved before Alpine init evaluates the bindings — no flicker on first paint, no width jump on state change.

- **`ticker` rendered `++8.4%` for already-signed-string deltas.** The delta-formatting block prepended `+` to positive deltas, then interpolated the original input verbatim — inputs that already carried an explicit sign rendered with double prefix. Strip leading `+` from string deltas before re-deriving the sign from the numeric value. Both `"8.4"` and `"+8.4"` now render identically as `+8.4%`. Negative signed strings (`"-1.2"`) keep their leading `-`. Numeric inputs (int / float) and unsigned strings remain unchanged.

- **`app-shell` defaulted to a width that didn't fill its preview / page wrapper.** Added `w-full` to the shell's base classes so it correctly fills its parent in both preview and live-page contexts.

- **`code-block` defensive styling against inherited inner `<pre>` / `<code>` background.** Added `bg-transparent` and `radius-none` on the inner elements to prevent host-page CSS from bleeding through.

- **WCAG 1.4.3 contrast sweep for soft-bg foreground tokens.** Round-2 polish on calendar week-view chips, finance cells, opacity-overlaid cards, and CTA buttons — every soft-tinted background now pairs with a foreground token that meets the 4.5:1 ratio in both light and dark mode. Plus a P0 fix to `dist/wirekit.css` (`:root {}` instead of `@theme {}` so a plain `<link rel="stylesheet">` load works without the build pipeline).

- **A11y polish across docs**: center component fills its parent, sandbox title de-collides, callout becomes a `<section>` for proper landmark semantics, kanban-column scroll body gets the missing scroll-region a11y wiring, listbox-vs-region heuristic clarification.

- **Description-leak sanitizer in three docs frontmatters** plus a permanent CI guard so future leaks are blocked at commit time.

### Changed

- **Component docs now demonstrate every component's preview-friendly props directly in the headline `:::preview` blocks instead of relying on docs-app post-processing.** Six component pages updated:
  - `docs/components/drawer.md` / `docs/components/alert-dialog.md` / `docs/components/command-palette.md` — preview content wrapped in `<div x-data>` so the trigger buttons' `@click="$dispatch(…)"` directives have an Alpine scope ancestor (previously the docs site post-processor injected one at runtime).
  - `docs/components/toast.md` — every preview now uses the new `eventScope="[data-wk-toast-scope]"` prop on `<x-wirekit::toast-region>` plus a sibling `<div data-wk-toast-scope x-data>` wrapper. Cross-talk between the four toast previews on the same docs page is now scoped at the source (each region only handles events from its own preview), so the docs site no longer needs runtime event filtering.
  - `docs/components/table.md` — replaced the decorative-only "Sortable headers" preview with a fully interactive `alpine-sort` demo. Three rows of demo data; click any column header to cycle through ascending → descending → unsorted.
  - `docs/components/action-bar.md` — the `## Usage` preview now uses `mode="static"` so it renders inline in the docs preview frame; intro paragraph documents the default `mode="floating"` viewport-pinned behaviour and links to the `## Layout Modes` section for the full prop description.

- **Icon documentation now mentions both stackable extension presets (`heroicons-app`, `heroicons-marketing`) consistently from the top of the page.** Earlier the stackable presets only surfaced in the Available-Presets table near the bottom while top-of-page sections (Requirements tip, Configuration block, Switching Presets) referenced base presets only — discoverability gap closed. README and `docs/integration.md` updated in lockstep; `docs/integration.md` also renamed its "Marketing Icon Set" subsection to "Stackable Heroicons Extensions" and now covers `heroicons-app` symmetrically alongside `heroicons-marketing`.

- **`menubar` "Items with Links" preview** expanded from a single-trigger "Navigate" example to three top-level menus (File / Edit / Help). The single-trigger version rendered as visually nested rectangles ("button-in-a-button") because the menubar parent border had no other menus to balance against. Three menus mirror the canonical macOS / shadcn menubar pattern, give the parent border its purpose, and add pedagogical value.

- **`code` component documentation de-duplicated** — code-block sections that overlapped the dedicated `code-block` component page were stripped from `code.md` to leave a single source of truth.

- **`kbd` component documentation** intro paragraph expanded with concrete usage guidance.

- **Blueprint-index previews** carry an opt-in `navigable` attribute on the 12 blueprint-index preview fences so the docs site can wire the appropriate click affordance per preview.

- **3 starter recipes get curated `composed_from:` frontmatter** plus a docs-app-side cross-reference rendering that surfaces them as inline component pills inside the recipe body.

---

## [1.3.0] — 2026-04-26

Major release covering blueprint primitive components, a three-tier automated
accessibility testing pipeline, comprehensive dark-mode verification, an Artisan
command suite for scaffolding / diagnostics / asset publishing / AI-tooling
integration / machine-readable manifests, the Livewire sandbox primitives library,
per-component keyboard interaction tables / pitfalls / changelogs across all 109
component pages, and a security-hardening sweep across `target="_blank"` link
rendering plus the JSON-encoder layer.

No breaking changes — fully backward-compatible with v1.2.x.

### Added

- **`<x-wirekit::*>` keyboard interaction tables across all 109 component
  docs.** Every component documentation page now ships a `## Keyboard
  Interaction` H2 section. Native form controls get browser-semantic tables;
  custom interactive widgets (modal, drawer, dropdown, command-palette,
  tree-view, image-compare, ...) get tables verified against the
  corresponding `resources/js/components/{name}.js` keydown listeners and
  the Blade `x-on:keydown` directives — never guessed from the WAI-ARIA
  pattern alone. Pure presentational primitives carry an explicit one-line
  note rather than an omitted section, so future readers do not wonder
  whether the section was forgotten or genuinely empty. A permanent CI
  guard in `DocsPreviewRenderTest` H2-anchored greps every component doc
  and blocks merges that ship a new component without the section.

- **Per-component `## Pitfalls` sections** — curated "what NOT to do" lists
  on 76 component pages covering the highest-leverage gotchas: form
  bindings, variant misuse, overlay nesting, screen-reader pitfalls, and
  component-specific traps mined from past audit findings. Trivial
  primitives (kbd, mark, divider, spacer, visually-hidden, aspect-ratio,
  layout wrappers) intentionally skipped — opt-in, no CI guard.

- **Per-component `## Changelog` sections** — auto-generated by the new
  `wirekit:generate-changelogs` command, mining filtered Git history
  (`feat`/`fix`/`refactor`/`perf`/`a11y`/`security` prefixes only) against
  each component's Blade source. 105 docs populated on first run. Sub-
  component directories are followed (`card.header` → `card/header.blade.php`).
  Output is bracketed by `<!-- changelog:start -->` / `<!-- changelog:end -->`
  HTML markers and is fully idempotent — re-running produces md5-identical
  files. Hand-written prose outside the markers survives. Permanent CI
  guard requires marker-pair presence whenever a `## Changelog` heading
  exists.

- **Sandbox primitives library** (`src/Sandbox/`) — reusable security-
  hardened render pipeline that the docs-app's live-preview iframe and
  any consumer project can build on:
  - **`SandboxRenderer::render($component, $props, $ip): RenderResult`** —
    main entry point. Validates → sanitizes → renders → audit-logs.
    Returns `RenderResult` (success or 422-shaped rejection); never
    throws.
  - **`PropsValidator`** — enforces per-component prop schema, type-checks,
    rejects strings >10 KB and arrays nested >5 deep (DoS defence),
    HTML-escapes every string defence-in-depth so even a slot using
    `{!! !!}` cannot surface raw payload content.
  - **`ComponentAllowlist`** — strict kebab-case regex + `ComponentRegistry`
    cross-check + sandbox-schema presence guard. Path-traversal
    characters, namespace separators, whitespace, uppercase — all
    rejected with 422-shape, never 500.
  - **`SandboxSchemaRegistry`** — in-memory registry of per-component
    prop allowlists with `allowed_values` enums. Initial coverage of
    11 starter components (button, badge, callout, alert, card, code,
    code-block, kbd, heading, text, link); the renderer is functional
    with whatever schemas are seeded, full 109-component coverage will
    follow incrementally.
  - **`SandboxAuditLog`** — file-based daily-rotating log
    (`storage/logs/sandbox/YYYY-MM-DD.log`). IPs sha256-truncated to 16
    chars so logs are useful for rate-pattern auditing but not for
    tracking individuals.
  - **`RenderResult` / `ValidationResult`** — immutable result objects
    with public-readable `ok` / `violations` / `html` / `schema`
    properties so the docs-app's `SandboxController` consumes them via
    `get_object_vars()`.
  - 15 security-focused Pest cases cover a 9-vector threat model.

- **`<x-wirekit::ticker>` dark-mode contrast fix** — switched the delta
  text from the bare `--color-wk-success` / `--color-wk-danger` foundation
  tokens to the `*-text` variants (which are calibrated for ≥4.5:1 WCAG
  1.4.3 contrast against surface tokens in BOTH light and dark mode).
  Covers a regression surfaced by the dark-mode browser-coverage tests.

- **Stackable icon presets sweep across blueprints + layouts** — `bolt`,
  `sparkles`, `shield-check`, and the wider marketing alias set now used
  in `docs/layouts/marketing/landing.md` and adjacent files. Sample-app
  config flipped to `'presets' => ['heroicons', 'heroicons-marketing']`
  to mirror the docs-app production config.

- **Layout + blueprint frontmatter schema extended** — every
  `docs/layouts/**/*.md` and `docs/blueprints/**/*.md` (excluding indexes
  and partials, 66 files) now carries five new metadata fields:
  `category` (frozen 15-vertical enum), `tags` (filterable string array),
  `dependencies` (component-name array, auto-extracted from
  `<x-wirekit::*>` references in the body), `responsive` (bool),
  `dark_compatible` (bool). Powers the upcoming `/blocks` gallery filter
  UI in the docs site. Permanent CI guard validates every block file's
  frontmatter against the schema.

- **`.cursor/rules/wirekit.mdc`** — single-file (~150-line) Cursor rules
  ruleset covering component invocation syntax, the Intent × Surface
  variant system, design tokens, icon usage, layout primitives,
  typography primitives, modal/drawer/dropdown trigger patterns,
  accessibility defaults, Livewire integration patterns, browser-support
  baseline, and the full CLI. Cursor / Codeium / other native `.mdc`
  editors pick up the rules automatically for every `*.blade.php` and
  `*.css` file in the project.

- **Blocks Gallery sidebar entry** in `docs/blueprints/_meta.json` and
  `docs/layouts/_meta.json` Overview groups (`{"title":"All Blocks
  (Gallery)","slug":"blocks"}`) so the docs site sidebar links to the
  new `/blocks` gallery from both sections.

- **`docs/cli.md` consolidated reference** — single page covering all
  fourteen `wirekit:*` Artisan commands with a quick-reference table at
  the top and per-command sections (signature, purpose, idempotency
  notes, exit-code semantics).

- **`php artisan wirekit:component {name}`** — scaffolds a custom
  Blade component derived from a WireKit base into
  `resources/views/components/custom/{name}.blade.php`. `--base` flag
  picks the source (defaults to `{name}`); `--force` allows
  overwriting an existing custom file. Resolves both flat
  (`button`) and dotted (`card.header`) base names.

- **`php artisan wirekit:publish-icons {preset}`** — targeted icon
  publishing. Copies a single preset's SVG directory from
  `vendor/{package}/resources/svg/` to
  `public/vendor/wirekit/icons/{preset}/`. Refuses with a precise
  `composer require ...` fix line when the underlying icon-set
  package is not installed. Supports `heroicons`, `heroicons-app`,
  `heroicons-marketing`, `lucide`, `phosphor`, `tabler`.

- **`php artisan wirekit:doctor`** — alias for `wirekit:verify` under
  the more conventional Laravel-ecosystem name. Both registrations
  stay in parallel for backward compatibility — existing CI scripts
  and docs that reference `wirekit:verify` keep working.

- **`php artisan wirekit:cursor-rules`** — copies the package's
  `.cursor/rules/wirekit.mdc` into the consumer project's
  `.cursor/rules/` directory. `--force` to overwrite an existing
  copy.

- **`php artisan wirekit:export-api-map [--pretty]`** — emits an
  AI-friendly hierarchical sitemap covering eight groups: components,
  themes, fonts, icons, layouts, blueprints, recipes, and commands.
  Superset of `wirekit:export-json`. Output is XSS-safe via
  `JSON_HEX_TAG`. Designed for MCP servers and other AI tooling that
  need a single entry point to enumerate every WireKit surface.

- **`php artisan wirekit:export-blocks [--pretty]`** — emits a
  machine-readable JSON manifest of every layout + blueprint with
  its frontmatter metadata (category, tags, dependencies, responsive,
  dark_compatible) plus generated `preview_url` (docs.wirekit.app)
  and `source_url` (GitHub raw URL). Consumed by the docs-app's
  `/blocks` gallery UI for filterable browsing.

- **`php artisan wirekit:generate-changelogs [--dry-run]`** —
  regenerates per-component `## Changelog` sections in every
  `docs/components/*.md` file. See the per-component changelog entry
  above for the algorithm.

- **README.md Documentation table** — new row pointing at the
  consolidated CLI reference page (`docs/cli.md`).

- **Blueprint primitive components (8)** — `<x-wirekit::price>` (currency
  formatting with size variants), `<x-wirekit::date-separator>` (timeline/chat
  date divider), `<x-wirekit::reaction>` (emoji reaction button with count),
  `<x-wirekit::ticker>` (live data ticker with delta indicator),
  `<x-wirekit::toolbar>` (button group bar with slots),
  `<x-wirekit::message>` (chat/thread message bubble with alignment),
  `<x-wirekit::kanban>` and `<x-wirekit::kanban-column>` (kanban board with
  column composition). All use design tokens exclusively, support the
  Intent × Surface API where applicable, and follow WAI-ARIA patterns.
- **Three-tier accessibility testing pipeline** — automated axe-core scanning
  across all 76 blueprint and layout preview blocks. Tier 1 (14 representative
  previews) runs on every PR as a gate. Tier 2 (all 76 previews) runs
  on-demand via `--group=full-a11y`. Tier 3 (dark-mode scans) runs nightly.
  Dynamic `/preview/{section}/{path}/{index}` route in sample app renders
  isolated preview blocks for targeted axe analysis.
- **Dark-mode accessibility scanning** — dedicated test suite validates color
  contrast, focus indicators, and semantic color usage across all preview blocks
  rendered with the `.dark` class. Runs nightly via `--group=dark-a11y`.
- **Preview data seeding** — blueprint previews that iterate over collections
  (`$products`, `$orders`, `$customers`, etc.) now render with realistic
  placeholder data instead of empty loops.
- **Password input accessibility** — toggle button has a static `aria-label`
  fallback for pre-Alpine-hydration accessibility scans.
- **Toggle auto-labeling** — component auto-generates an `aria-label` from
  the `name` prop when neither `label` nor `aria-label` is provided.
- **Documentation** — 7 new component pages (price, date-separator, reaction,
  ticker, toolbar, message, kanban) with 49 preview blocks. Config entries
  for `price` and `ticker` size defaults. Blueprint index pages now link
  to individual blueprint pages (58 cards carry `href`).
- **96 new tests** across blueprint primitives and accessibility coverage,
  bringing the total test suite to 1412 tests.

### Changed

- **`Pushery\WireKit\Sandbox\RenderResult`** now carries a public-
  readable `?array $schema` property in addition to `ok` / `html` /
  `violations`. `SandboxRenderer::success()` echoes the per-component
  schema back so consuming UIs (the docs-app iframe page, custom
  preview surfaces) can render a prop-editor without a second
  round-trip to the schema registry.

- **Layout + blueprint Markdown files** — each non-index, non-partial
  page now carries the this release frontmatter schema (`category` / `tags` /
  `dependencies` / `responsive` / `dark_compatible`). Existing fields
  (`title` / `description` / `visibility` / `draft`) are unchanged.

- **Blueprint and layout documentation refactored** — all 74 documentation
  pages (48 blueprints across 10 verticals, 26 layouts) converted from raw
  HTML to WireKit component composition. Zero Tailwind utility classes on
  raw HTML elements; all styling uses design tokens and component props.
- **`target="_blank"` auto-protection hardened** — the `rel` attribute
  injection in all 13 link-rendering components (Button, Dropdown Item,
  Command Palette Item, Menubar Item, Navigation Menu Item, Navigation Menu
  Link, Navbar Item, Sidebar Item, Link, Brand, Card) now uses an explicit
  override pattern that prevents caller-supplied `rel` values from silently
  defeating the `noopener noreferrer` protection. Coverage extended to four
  additional components that were previously missing the pattern: Link,
  Navbar Item, Navigation Menu Link, and Sidebar Item.
- **Chart.js dark-mode refresh** — `chart.update()` replaces
  `chart.update('none')` in the MutationObserver callback. Chart.js v4's
  `'none'` mode skips the style-resolver pass when only color properties
  change, leaving stale colors rendered. The observer now watches both
  `<html>` and `<body>` for `.dark` class changes, supporting both mounting
  conventions.

### Fixed

- **WCAG 1.4.3 dark-mode contrast on `<x-wirekit::ticker>` delta text** —
  see Added entry above. Browser-coverage tests on
  `analytics/dashboard` and `finance/portfolio` previously flagged
  3.66:1 / 4.05:1 ratios against dark surface tokens (fails the 4.5:1
  AA threshold for small text). Fix uses the `*-text` token variants
  which are calibrated for ≥4.5:1 in both modes.

- **Inline `color:var(--color-wk-{danger|success})` in 14 blueprint and
  layout files** — same WCAG 1.4.3 issue at the docs-content layer.
  Mechanical sweep across `docs/blueprints/` and `docs/layouts/`
  converted both `color:` inline styles AND `text-[var(--color-wk-...)]`
  Tailwind classes to the `*-text` variants. Affected files:
  `finance/{portfolio,trading-terminal,instrument-detail,markets}`,
  `analytics/dashboard`, `helpdesk/reports`. Component-internal usages
  (icon markers, tinted backgrounds) intentionally NOT swept — those
  hit the 3:1 non-text threshold or use 12% color-mix backgrounds.

- **10 blueprint preview wrappers were clipped to 16 rem** — a
  hardcoded `height:16rem;` declaration on the outer wrapper of 10
  files clamped the rendered preview to ~304 px and clipped ~38 px
  of layout content. Mechanical sweep removed the declaration; the
  surrounding `overflow:hidden` is preserved so rounded corners
  still clip the inner content. Layouts now render at their natural
  heights (400–700 px each).

- **Sample app icon config** — `sample/config/wirekit.php` flipped from
  the legacy single-string `'preset' => 'heroicons'` to the stackable
  `'presets' => ['heroicons', 'heroicons-marketing']` so the sample
  app mirrors the docs-app production config and the marketing-icon
  blueprints (`landing.md`, etc.) render correctly under browser
  tests.

- **CTA accent variant dark-mode contrast** — the accent variant used a
  hardcoded `text-white` class that fails when `--color-wk-accent` inverts
  in dark mode. Now uses `text-[var(--color-wk-accent-fg)]` which
  auto-switches correctly.
- **Section accent background token** — the accent background variant
  referenced a non-existent `--color-wk-primary` CSS variable. Replaced
  with `--color-wk-accent` background and `--color-wk-accent-fg` text
  color, matching the established pattern used by CTA and Badge.
- **Blueprint card opacity contrast** — pipeline and kanban preview cards
  used `opacity: 0.7–0.75`, which compounds with muted text color to push
  contrast below the WCAG 2.2 AA 4.5:1 threshold. Replaced the opacity
  technique with explicit `bg-subtle` + `border-subtle` surface tones —
  same dim-card visual without opacity blending, so text-muted retains
  its full contrast against the surface.
- **Badge variant validation** — 120+ documentation previews used invalid
  badge variants (`accent`, `muted`, `outline`, `default`) that silently
  fell back to the first allowed value in production mode. All corrected
  to valid variants (`info`, `neutral`).
- **Text size validation** — 14 occurrences in error page layouts used
  `size="6xl"`, `size="3xl"`, or `size="2xl"` which exceed the text
  component's allowed range (xs–xl). Replaced with `size="xl"` and inline
  `font-size` overrides to preserve visual appearance.
- **Dropdown trigger ARIA** — `aria-haspopup`, `aria-expanded`, and
  `aria-controls` were placed on a non-interactive `<div>` wrapper. Moved
  to the inner interactive element via `x-init`.
- **Progress bar accessible name** — component only wired `aria-labelledby`
  when the `label` prop was set. Usages without labels now receive a
  sensible `"Progress"` default.
- **App-shell sidebar backdrop** — the mobile sidebar dim overlay was
  traversable by screen readers, defeating the focus-trap intent. Added
  `aria-hidden="true"`.
- **Decorative image accessibility** — paired `alt=""` with
  `aria-hidden="true"` on decorative images in QR code, storefront, and
  product admin documentation previews.
- **Blueprint index page UX** — removed stray `:` rendering artifacts from
  12 index pages (malformed `::::` closing fences). Moved screen-count
  badges inline with headlines.
- **Ecommerce blueprint placeholder images** — 38 empty `<div>` elements
  (gray backgrounds with no content) across 9 ecommerce blueprint previews
  replaced with `<img>` tags using the first-party placeholder service.
  Each placeholder carries a distinct label and background color per product.
  Affected pages: storefront-home, storefront-product, storefront-collection,
  storefront-cart, storefront-checkout, admin-dashboard, admin-products,
  admin-product-editor, admin-order-detail.
- **Blueprint index card links** — all 10 vertical card `href` values in
  the top-level blueprint index now use the explicit `/index` suffix to
  match the canonical `_meta.json` slug format.

- **`dist/wirekit.css` parses correctly when loaded via `<link>`.**
  Previously the entire token palette lived inside a Tailwind v4
  `@theme {}` compiler block. Browsers correctly skip unknown at-rules
  per the CSS spec, which meant the documented "fastest path" — using
  the `@wirekitStyles` Blade directive that embeds the file via
  `<link rel="stylesheet">` — left zero `--color-wk-*` variables defined
  in the CSSOM. Components rendered without color tokens. The file now
  emits a standard `:root {}` (light) and `.dark {}` (dark) block
  directly, so both consumption paths resolve identically: the
  `@wirekitStyles` directive AND `@import` from `app.css`. The
  previously-misleading "Do NOT @import this file" warning in
  `wirekit:doctor` / `wirekit:verify` is gone — replaced with a
  positive `✓ wirekit.css is @import-ed in app.css (valid setup path)`
  line. The `@custom-variant dark (&:where(.dark, .dark *));` directive
  is preserved at the top of the file (harmless under `<link>`, useful
  under `@import` for Tailwind `dark:` variant support).

- **WCAG 1.4.3 contrast — light-mode `*-text` and `text-muted` tokens
  recalibrated.** Three "soft-bg foreground" tokens were below the AA
  4.5:1 threshold against the 12% `color-mix()` soft-tone backgrounds
  used by badge/alert/callout/feature/message/reaction/toast-region,
  and `text-muted` was below threshold on `bg-muted`:
  - `--color-wk-success-text`: green-700 → green-800. Was 4.33:1 on
    soft-success bg, now ~6.17:1.
  - `--color-wk-danger-text`: red-500 → red-700. Was 3.89:1 on
    soft-danger bg, now ~5.13:1.
  - `--color-wk-warning-text`: amber-700 → amber-800. Was 4.41:1 on
    soft-warning bg, now ~6.04:1.
  - `--color-wk-text-muted`: neutral-500 → neutral-550. Was 4.26:1 on
    `bg-muted` (#f7f7f7), now ~6.13:1. `text-subtle` and
    `text-placeholder` are unchanged (they only appear on white where
    neutral-500 already meets 4.74:1).

- **Bare `text-[var(--color-wk-{success,warning,danger})]` on text content
  swept to `*-text` variants.** Affected: `<x-wirekit::text>` semantic
  variants (`success` / `warning`), `<x-wirekit::stat>` `up` trend,
  `<x-wirekit::price>` delta intent (`success` / `danger`),
  `<x-wirekit::feature>` soft tones (`success` / `warning`), plus
  blueprint usages in `hr/dashboard`, `hr/time-off`, `mail/inbox`,
  `helpdesk/reports`, `analytics/dashboard`. Decorative `aria-hidden`
  SVG icons inside alert/callout/toast-region/code-block/rating left at
  the bare tone (graphic-element semantics, 3:1 threshold via
  WCAG 1.4.11).

- **Calendar event chips no longer render white text on green-500 /
  amber-500.** The month-view and week-view event chips
  (`<div style="background: var(--color-wk-success); color: var(--color-wk-bg)">`)
  yielded 3.21:1 / 2.13:1 — fail. Swept across both calendar
  blueprint pages and the sample app's `blueprint-calendar.blade.php`
  (~20 occurrences total) to use `color: var(--color-wk-success-fg)` /
  `color: var(--color-wk-warning-fg)` (zinc-900 on the tone bg —
  ~6.95:1 / ~9.03:1). Same change applied to the finance blueprint's
  inline `<td style="color: var(--color-wk-success)">` cells (now
  `color: var(--color-wk-success-text)`).

- **Calendar week-view chip subtexts collapsed.** The
  `<span ... opacity: 0.8">Studio</span>` / `</span>Zoom</span>` opacity-blended
  subtexts dropped contrast below 4.5:1 in light mode (4.23:1 against the
  tone bg) and tripped axe-core's iframe-bg detection in dark mode.
  Replaced the two-line `Primary` + opacity-dimmed subtext pattern with
  a single-line `Primary · Subtext` middle-dot label that passes both
  modes at the full token contrast.

- **Centered-CTA preview button on dark accent section.** The
  `layouts/partials/cta.md` "Simple Centered" preview placed a
  `variant="ghost"` button inside a dark-accent
  `<x-wirekit::section background="accent">` — `text-on-text-on-dark`
  yielded 1.1:1. Swapped to `variant="secondary"` (uses `bg-muted` light
  bg + dark text, passes on dark accent). Other ghost-button usages
  inside light-bg toolbars are unaffected.

### Tooling

- **Permanent CI guard** for the `## Keyboard Interaction` H2 section
  on every component doc.

- **Permanent CI guard** for `## Changelog` marker-pair integrity on
  any component doc carrying the heading.

- **Permanent CI guard** validating layout + blueprint frontmatter
  against the this release schema (allowed-categories enum, `dependencies`
  references real components, bool fields are bool).

- **Permanent CI guard** for forbidden-pattern leaks: every public
  file (`README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`,
  `composer.json`, `config/`, `src/`, `resources/`, `dist/`, `docs/`)
  is greppped for internal-only references (work-tracking numbers,
  agent / AI mentions, dev-only path references, dated changelog headings,
  pre-release commit buckets). Five test cases — zero opt-out flag.

- **Per-component changelog sanitizer.** The `wirekit:generate-
  changelogs` codegen now strips internal-only references from every
  commit subject before writing it to public docs (work-tracking
  numbers, briefing references, agent mentions, claude.ai URLs,
  internal paths, PR refs). Version headings carry the version only
  ('### this release') with no date suffix, and pre-release commits are
  excluded from public output entirely.

### Security

- **`/components.json` JSON encoder hardened with `JSON_HEX_TAG`** —
  brings `wirekit:export-json` in line with the existing
  `wirekit:export-api-map` and `wirekit:export-blocks` contracts.
  Without `JSON_HEX_TAG`, a component description containing
  `</script>` could break out of a `<script type="application/ld+json">`
  block where the manifest is embedded. Same XSS vector class as the
  one closed in v1.3.0 for the breadcrumb component.

- **Test suite growth:** v1.3.0 baseline of 1495 → this release 1545 tests
  (+50 new cases). 6472 assertions. Pint clean. markdownlint clean
  across all 252 Markdown files. Forbidden-pattern leak guard 0 hits
  across every public surface.

---

## [1.2.2] — 2026-04-20

Patch release with documentation improvements and a sync pipeline fix.

### Fixed

- **Aspect Ratio docs** — replaced abstract badge-in-grey-box previews with real
  images. Shows side-by-side ratio comparison (16:9, 4:3, 1:1) and
  portrait/ultrawide examples so the component's purpose is immediately visible.
- **App Shell docs** — all four previews constrained to fixed height (280–320 px).
  The component uses `min-h-screen` internally, which filled the entire viewport
  in the docs preview frame, leaving massive empty whitespace below the content.
- **Layout docs consistency audit** — standardized 16 previews across 6 component
  docs pages (Grid, Stack, Row, Divider, Section, Spacer). Spacing and alignment
  demos now use cards consistently; contextual demos (navigation, toolbars, tags)
  retain their purpose-appropriate components.
- **Center docs** — all three previews now show a muted background on the center
  container so the centering boundary is immediately visible.
- **Reverse briefing sync** — the bidirectional docs sync workflow silently
  discarded briefings from the docs repo due to a branch mismatch on
  `repository_dispatch` events. Added explicit `ref: develop` checkout and
  removed `continue-on-error` so failures surface.

---

## [1.2.1] — 2026-04-20

Patch release to fix the automated release pipeline.

### Fixed

- **Release workflow tag push** — `git push origin "${TAG_NAME}"` failed with
  `src refspec matches more than one` when both the release branch and the new
  tag shared the same name. Changed to `git push origin "refs/tags/${TAG_NAME}"`
  to explicitly push the tag reference.

---

## [1.2.0] — 2026-04-20

Major feature release. WireKit now covers full-page composition: a 10-component
layout primitives system, a 9-component typography primitives system, app-shell
scaffolding, and a marketing-page toolkit — plus a new unified `intent × surface`
variant API, a component registry, five new artisan commands, and a rewritten
release pipeline.

### Added

- **Layout primitives (10 components)** — `<x-wirekit::container>` (width-constrained
  wrapper with max/padding/center props), `<x-wirekit::stack>` (vertical flex),
  `<x-wirekit::row>` (horizontal flex), `<x-wirekit::grid>` (responsive column
  syntax `cols="1 sm:2 lg:3"`), `<x-wirekit::section>` (full-width with background
  and divider variants), `<x-wirekit::spacer>` (flex-grow), `<x-wirekit::divider>`
  (horizontal/vertical with label and variants), `<x-wirekit::center>` (flex
  centering), `<x-wirekit::aspect-ratio>` (native CSS `aspect-ratio`), and
  `<x-wirekit::visually-hidden>` (sr-only wrapper).
- **Typography primitives (9 components)** — `<x-wirekit::heading>` (h1–h6 with
  auto-sizing, accent, tracking), `<x-wirekit::text>` (body text with size,
  variant, weight, align, truncate, line-clamp), `<x-wirekit::link>` (styled
  anchor with external-link detection), `<x-wirekit::code>` (inline monospace),
  `<x-wirekit::code-block>` (multi-line with copy button and filename),
  `<x-wirekit::kbd>` (keyboard key indicator), `<x-wirekit::list>` (ul/ol with
  spacing), `<x-wirekit::blockquote>` (left-border with citation), and
  `<x-wirekit::mark>` (text highlight wrapper).
- **`<x-wirekit::highlight>`** — typography helper that highlights query matches
  inside a block of text. Pairs with the Prose component's new `variant` prop.
- **App Shell components (6 components)** — `<x-wirekit::app-shell>` (full-page
  layout with responsive sidebar toggle), `<x-wirekit::header>` (sticky header
  with optional container), `<x-wirekit::main>` (content area with padding
  variants), `<x-wirekit::brand>` (logo + name link), `<x-wirekit::profile>`
  (avatar + name display), and `<x-wirekit::sidebar.toggle>` (hamburger button).
- **Marketing components (5 components)** — `<x-wirekit::hero>` (landing-page
  hero with variants, gradient, slots), `<x-wirekit::feature-grid>` (responsive
  feature-card grid), `<x-wirekit::feature>` (individual card),
  `<x-wirekit::cta>` (call-to-action banner with dark and accent variants), and
  `<x-wirekit::footer>` (columns, brand, legal slots).
- **Unified Intent × Surface variant system** — new `intent` and `surface` props
  on Button and Badge. Six intents (`neutral`, `accent`, `success`, `warning`,
  `danger`, `info`) × five surfaces (`filled`, `soft`, `outline`, `ghost`, `link`)
  generate consistent combinations via a central `VariantResolver`. The legacy
  `variant=` API is preserved for full backward compatibility.
- **`ComponentRegistry`** — central catalog of every WireKit component with
  category and description metadata. Anti-drift tests enforce registry ↔
  filesystem consistency (every Blade file has an entry, no stale entries,
  valid categories, non-empty descriptions).
- **`php artisan wirekit:list`** — lists all components grouped by category.
- **`php artisan wirekit:show {name}`** — displays props, sub-components, and
  docs path for a given component.
- **`php artisan wirekit:install`** — one-command setup: publishes config and
  assets, prints layout directive snippet, adds published assets to `.gitignore`.
- **`php artisan wirekit:theme {preset}`** — injects a theme preset's CSS block
  into the consumer's `app.css`.
- **`php artisan wirekit:make {name}`** — scaffolds a Livewire page pre-wired
  with WireKit components.
- **Design tokens** — new spacing scale (`--space-wk-xs` through `--space-wk-2xl`),
  container max widths (`--size-wk-container-sm` through `--size-wk-container-2xl`),
  `--text-wk-3xl`, `--font-wk-heading-line-height`, and semantic colors
  (`--color-wk-bg-inverse`, `--color-wk-text-inverse`, `--color-wk-border-strong`,
  `--color-wk-warning-bg`) with dark-mode counterparts.
- **Accessibility sections in docs** — Button, Input, Textarea, and Label pages
  document label pairing, `aria-invalid`/`aria-describedby` wiring,
  `:user-invalid` styling, focus-visible rings, disabled states, icon-only
  `aria-label` guidance, external-link auto-protection, and required-indicator
  semantics.
- **162 new tests** across the 19 layout and typography primitives, bringing
  the total test suite to 1316 tests (3712 assertions).

### Changed

- **`target="_blank"` auto-protection** — all link-rendering components
  (Button, Dropdown Item, Brand, Card, Navigation Menu Item, Link, Menubar Item,
  Command Palette Item) now automatically inject `rel="noopener noreferrer"`
  (tabnabbing prevention) and a `<span class="sr-only">(opens in new tab)</span>`
  screen-reader hint whenever `target="_blank"` is set. No consumer changes
  needed. Components with both button and link modes include a guard so the
  injection only fires when `href` is present.
- **`<x-wirekit::prose>`** — gained a `variant` prop for tighter integration with
  the new typography primitives.
- **Release pipeline** — the public-repo release workflow now opens a pull
  request on `pushery/wirekit` from a release branch, squash-merges it into
  `main` with the CHANGELOG section as the commit body, and tags the squashed
  commit. Result: one clean squash commit per release on public main with the
  full CHANGELOG embedded. Retries are safe.

### Fixed

- **Grid and Feature Grid responsive classes missing from consumer bundles** —
  `grid.blade.php` and `feature-grid.blade.php` built Tailwind class names via
  runtime PHP string concatenation (`"{$breakpoint}:grid-cols-{$value}"`).
  Tailwind's content scanner only sees literal strings, so these classes were
  dropped from consumer CSS and previews rendered as stacked items instead of
  responsive grids. Replaced with a literal class map covering all valid
  breakpoint × column combinations (Grid: 72 entries; Feature Grid: 36 entries).
  Invalid tokens surface via `WireKit::validateProp()`.
- **Layout-doc preview rendering** — 40 `<x-wirekit::card>` occurrences in
  container, grid, stack, row, section, spacer, and divider docs wrapped raw
  content directly in the card without `<x-wirekit::card.body>`. Card provides
  border/radius/background but not padding — previews rendered as thin-bordered
  boxes with cramped text. All 40 occurrences now use `card.body`. Aspect-ratio
  preview refactored to a styled centering frame.
- **Feature component test and docs coverage** — added 8 render tests and a
  dedicated `docs/components/feature.md` page with three previews. Registered
  in the `DocsPreviewRenderTest` minimum-previews guard.
- **Documentation sidebar coverage** — five components (Code Block, Header, Main,
  Brand, Profile) now have their own docs pages and dedicated sidebar entries.
  Previously their README rows pointed to parent pages, leaving them invisible
  in the navigation.
- **README component links** — the Feature row now links to its own docs page
  instead of Feature Grid. Highlight component added to the Typography table.

---

## [1.1.1] — 2026-04-18

Feature release with a complete theme overhaul, two new components, and
accessibility improvements across the board.

### Added

- **`<x-wirekit::image-compare>`** — new before/after image comparison slider
  with horizontal and vertical orientation, pointer/touch drag, full WAI-ARIA
  Slider Pattern keyboard support, `wire:model` binding (deferred, live, and
  debounced), screen-reader live region, reduced-motion guard, and four
  personalization blocks. Ships in both the full and core JS bundle.
- **Liquid Glass extension** — optional glassmorphism module installed via
  `php artisan wirekit:glass install`. Tier 1 provides frosted-glass
  `backdrop-filter` effects (all browsers). Tier 2 adds SVG `feDisplacementMap`
  refraction (Chrome/Chromium only). CSS classes: `.wk-glass`,
  `.wk-glass-refract`. Blade component `<x-wirekit::glass />` for the layout
  head.
- **VT323 font** — bundled as 21st locally served font (OFL 1.1 license).
- **Modal and drawer headers** now auto-render a close button by default.
  The button respects the `dismissible` prop and can be disabled via
  `:close="false"` on the header. Personalizable via
  `WireKit::personalize('modal.header', ['close' => '...'])`.

### Changed

- **Complete theme overhaul** — all 7 presets rewritten with WCAG 2.2 AA
  compliance verified for every text-on-surface, button-label, and
  semantic-message pair in both light and dark mode.
- **Default theme** migrated from zinc to neutral palette (zero chroma, no blue
  cast). Inter font set as default sans. Updated shadows, letter-spacing, and
  dark-mode borders.
- **Cupertino theme** — new Apple-aesthetic preset replacing Slick. Uses
  `-apple-system` font stack, iOS ease-out easing, Apple HIG dark colors,
  `blue-600` accent, and 0.5px hairline borders.
- **Minimal theme** — rewrite: 0px radius, 2px ring, neutral-200 input
  backgrounds for visible boundaries.
- **Soft theme** — rewrite: DM Sans font, violet accent, wider blur shadows,
  ease-out easing.
- **Material theme** — rewrite: M3 standard decelerate easing, indigo accent,
  Roboto font.
- **Brutalist theme** — rewrite: neutral palette, JetBrains Mono font, explicit
  border-color tokens fixing WCAG 1.4.11 contrast failures.
- **Retro Terminal theme** — rewrite with 3 WCAG fixes: ring width 1px→2px,
  danger-fg contrast fix, explicit success/warning foreground tokens.
- **Context menu** panel now teleports to `<body>` by default, consistent with
  Modal, Drawer, and other overlay components. Opt-out via `teleport="false"`.
- **Resizable panels** now support symmetric pair-drag in 3+ panel layouts —
  dragging a handle resizes both adjacent panels proportionally, matching
  industry-standard splitter behavior (VSCode, Figma, split.js).

### Fixed

- **Tour component** overlay and step panels now teleport to `<body>` via
  Alpine's `x-teleport`, matching the pattern used by Modal and Drawer. This
  ensures correct Floating UI positioning regardless of ancestor CSS transforms
  or containing blocks. Steps use `x-show` instead of `x-if` for simpler DOM
  lifecycle.
- **Tour step** no longer flickers at (0,0) on first show — a CSS fallback
  parks steps off-screen until Floating UI positions them.
- **Chart component** `style` attribute collision — caller-supplied `style` now
  merges correctly with the component's own height/background declarations.
- **Timeline** last-item trailing padding now correctly collapses when
  `after="true"` adds a continuation line.
- **Toggle** OFF track now meets WCAG 1.4.11 non-text contrast (changed from
  `bg-muted` to `border` token).
- **Checkbox and radio** hover border added for interactive discoverability on
  small 20×20 elements.
- **WCAG 2.2 AA contrast** fixes across all base tokens: `success-fg`,
  `warning-fg`, `text-subtle`, `text-placeholder`, and dark-mode `danger`
  values corrected.
- **Scroll-to-top preview** — button was invisible in documentation previews
  because `x-cloak` + `x-show` hid it before scroll events could fire. Added
  `forceVisible` prop that disables the scroll listener and keeps the button
  permanently visible.
- **Range slider** — added missing `pointercancel` event listener cleanup to
  prevent listener accumulation on interrupted drag gestures.
- **External doc link** — Liquid Glass cross-reference in theming guide changed
  from absolute URL to internal path for environment portability.

### Removed

- **Slick theme** — merged into Default. The theme set is now: Default,
  Minimal, Soft, Material, Brutalist, Retro Terminal, Cupertino.

---

## [1.0.1] — 2026-04-16

Patch release to correct bundle version headers and distribution metadata.

### Fixed

- **`dist/` version headers** bumped from `v0.2.0` to `v1.0.0` in all four
  bundles (wirekit.css, wirekit.js, wirekit.core.js, wirekit.esm.js).
- **Repository URLs** corrected across `README.md`, `CONTRIBUTING.md`, and
  `composer.json` for the Packagist distribution.

---

## [1.0.0] — 2026-04-16

First stable release of WireKit — a free, MIT-licensed UI component library for
Laravel Livewire built on Tailwind CSS v4, Alpine.js, and PHP 8.4+.

### Added

- **71 Blade components** spanning form controls, overlays, navigation, display,
  feedback, and specialized categories — every component uses design tokens
  exclusively (zero hardcoded colors), auto-switches between light and dark mode
  via `.dark` class, and supports personalization via `WireKit::personalize()`.
- **Form controls:** Input, Textarea, Select, Checkbox, Toggle, Radio Group,
  Range Slider, Color Picker, Date Picker, File Upload, Pin Input, OTP Input,
  Multi-Select, Combobox, Rich Text Editor (Tiptap).
- **Overlay components:** Modal, Drawer, Dropdown, Popover, Tooltip, Hover Card,
  Command Palette, Context Menu, Toast (with toast region), Confirm Dialog, Tour.
- **Navigation:** Navigation Menu, Breadcrumb, Pagination, Tabs, Stepper,
  Scroll-to-Top.
- **Display components:** Avatar, Badge, Card, Accordion, Timeline, Carousel,
  Image Compare, Scroll Area, Skeleton, Prose, QR Code, Clipboard Button,
  Collapsible, Separator, Resizable Panels, Tree View, Calendar, Stat Card,
  Data Table.
- **Feedback:** Alert, Progress Bar, Rating, Spinner.
- **Typography & layout:** Fonts (21 GDPR-compliant Google Fonts served locally),
  Icon system (4 presets: Heroicons, Lucide, Phosphor, Tabler with 26 semantic
  aliases), Chart (via Chart.js adapter).
- **Theming system** with ~80 CSS custom properties, 7 theme presets (Default,
  Minimal, Soft, Material, Brutalist, Retro, Cupertino), all WCAG 2.2 AA compliant
  in both light and dark mode.
- **Font system** — 21 locally bundled Google Fonts (10 sans, 5 serif, 6 mono),
  zero external requests, configurable via `config/wirekit.php`, served from the
  app's own domain for CSP and GDPR compliance.
- **Icon system** — pluggable SVG icon presets with resolved aliases, caching,
  and a `<x-wirekit::icon>` component that renders any icon by name.
- **Chart system** — adapter-based architecture with a Chart.js adapter, a
  class-based `<x-wirekit-chart>` component, and dark-mode-aware color tokens.
- **JavaScript bundles:** full bundle (Floating UI + focus-trap, ~76 KB),
  core bundle (chart + image-compare only, ~7.5 KB), and ESM bundle for
  tree-shaking.
- **Accessibility:** every interactive component follows WAI-ARIA Authoring
  Practices — proper `role`, `aria-*` attributes, keyboard navigation, focus
  management, screen-reader announcements via `aria-live` regions. Tour and
  modal use `role="dialog"`, combobox implements full ARIA 1.2 combobox pattern,
  all form components support error and description associations.
- **Personalization system** — three levels of customization: CSS variable
  overrides (theme presets), `WireKit::personalize()` for global class overrides,
  and `WireKit::scope()` for per-instance class overrides.
- **Reduced-motion support** — `@media (prefers-reduced-motion: reduce)` disables
  all WireKit animations and transitions, including skeleton pulse, progress bar
  indeterminate, and Alpine x-transition durations.

### Browser Support

Chrome 111+, Edge 111+, Safari 16.4+, Firefox 128+ — matching the Tailwind CSS v4
browser baseline.
