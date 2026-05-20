# Changelog

All notable changes to WireKit are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.1] — Unreleased

**Patch release.** Accessibility hardening sweep across every public docs page rendered on `docs.wirekit.app`. Targets the WCAG 2.2 AA contract WireKit promises in `README.md`. No API changes, no behaviour changes, no token changes — every existing developer drops in `v2.1.1` and observes a stricter screen-reader / keyboard-reader contract with no migration work.

### Fixed

- **`/recipes/toolbar-filter-bar` filter controls had no accessible name.** The two `<x-wirekit::select>` filters and the search `<x-wirekit::input>` on the toolbar recipe page shipped without a `label`, `aria-label`, or wrapping `<x-wirekit::field>` — axe-core's `select-name` rule fires critical for that shape because screen readers announce each filter as "blank, combobox" with no semantic context. Added `aria-label="Filter by status"` / `aria-label="Filter by category"` / `aria-label="Search products"` to both the rendered preview AND the developer-facing source snippet so copy-paste users inherit the fix automatically.

## [2.1.0] — 2026-05-20

**Minor release.** Strict-by-default install hygiene, full pre-flight validation, token-clobber detection, plus five new install flags (`--no-strict`, `--force`, `--ignore-failed-flags`, `--diff`, `--rollback`) for explicit developer control over the install lifecycle. Public CSS API catalog (`/extending/public-css-api`) + content-edge spine contract (`/extending/spine-contract`) ship as the canonical AI-tooling discovery surfaces for `wk-*` classes and layout participation. New `<x-wirekit::spine-aware>` helper wrapper + `WireKit::spinePadding()` static let developer-authored components opt into the page-edge spine with one line. ALSO ships marketing-primitive composition ergonomics: brand-bar gains `container` + `max` props, hero/cta gain responsive `size` props with mobile-tightened vertical padding, hero's gradient overlay is contained to the content wrapper (no more empty-dark-area class of bug on mobile), footer gains a `max` prop replacing the prior hardcoded `xl` inner width. A new canonical recipe at `/recipes/marketing-landing-page` shows the full composition, and a new explainer at `/extending/composition-patterns` documents the chrome-vs-content axis as a design contract. Command-surface consistency: every "Unknown X" error now emits a uniform Levenshtein-ranked Did-you-mean hint, `wirekit:theme default` succeeds (and removes any existing preset block) instead of returning FAILURE, `wirekit:list --category=Marketing` lists the four canonical conversion primitives (cta, feature, feature-grid, hero), `wirekit:component my-button` derives `--base=button` automatically, `wirekit:doctor` becomes a Symfony alias of `wirekit:verify` (both names resolve to the SAME command instance), and `<x-wirekit::cta>` / `<x-wirekit::hero>` runtime prop validation reuses the same suggestion contract. **Two behaviour changes** flagged below: `wirekit:install` now aborts (exit 2) on invalid flag values or token-clobber warnings, where v2.0.0 printed a warning and continued with exit 0; AND the `cta` / `feature` / `feature-grid` / `hero` components migrated from category `Display` to category `Marketing` — developers reading `wirekit:export-json` see the changed category strings. Developers who depended on the legacy "warn-and-proceed" semantics combine `--no-strict --ignore-failed-flags` for byte-stable v2.0.0 behaviour. Every other change is additive.

### Added

- **Comprehensive mobile-viewport browser-test coverage across every category where viewport behaviour matters.** Builds on the marketing-primitive mobile coverage with Tier-1 PR-gate `Tier1MobileGateTest.php` (6 cross-category representatives — hero, modal, sidebar, input, table, reading-spine) plus Tier-2 on-demand sweeps for **Overlays** (modal / drawer / popover / tooltip / dropdown / alert-dialog / command-palette / hover-card / context-menu), **Navigation** (navbar / sidebar / tabs / breadcrumb / stepper / pagination / menubar / navigation-menu / brand-bar + reading-toc hideBelow breakpoint test), **Forms** (input / select / combobox / textarea / checkbox / toggle / date-picker / time-picker / segmented-control / slider / file-upload / field — with WCAG 2.5.5 AAA 44×44 touch-target audit), **Reading family** (progress / spine / toc / minimap / bookmark / meta / shell), **Data display** (table / kanban / data-list / timeline / tree-view / stat / stats / sparkline / pagination / progress / ticker / price / calendar — with horizontal-overflow guard), AND a generic `ComponentPreviewMobileSweepTest` that scans every `docs/components/**` `:::preview` block at iPhone 14 Pro viewport. New shared `AssertsMobileViewport` trait centralises the `matchMedia` override + console-error capture + scroll-settle scaffold so per-test boilerplate stays minimal.
- **`docs/extending/public-css-api.md` — canonical Public CSS API catalog.** Single source of truth for every `wk-*` class WireKit emits to developers — via `dist/wirekit.css` literal selectors AND via Blade-template static-string emissions. ~40 catalogued classes across four groups (Layout / chrome markers, Reading family, Animation / motion, Display / loading), each with a stability tier (Stable / Provisional / Internal-with-exception) and customisation contract. Drift-audit guard at `tests/Feature/PublicCssApiDriftTest.php` enforces both directions — every shipped class has a row, every row references a real shipped class. Surfaces the catalog as machine-readable JSON via `wirekit:export-api-map`'s new `css-classes` group for AI tooling / IDE-extension consumption.
- **`docs/extending/spine-contract.md` — content-edge spine participation contract.** Documents the per-component inline-padding spine — which components join `--padding-wk-x-lg` (brand-bar, main, container, header, footer, cta, hero outer-only, spine-aware) and why others (sidebar, reading-toc, section's non-lg tiers) deliberately don't. Hero's "outer-yes / inner-no" asymmetry is now explicit; developers no longer measure the discrepancy by hand. Drift-audit guard at `tests/Feature/SpineParticipantDriftTest.php` enforces a `// wirekit:spine-participant` marker comment on every documented participant — adding a new spine-aware component without the marker fails the build.
- **`<x-wirekit::spine-aware>` — NEW opt-in spine wrapper for developer-authored components.** Pass any slot content; the outer `<div>` reads `--padding-wk-x-lg` so the content aligns with brand-bar / main / footer / etc. on the same vertical content edge. The `tier` prop (`sm` / `md` / `lg` / `xl`) reads a different `--padding-wk-x-*` tier when the developer wants to deliberately step off the spine. Helper-driven (uses the new `WireKit::spinePadding()` static) so a future spine-padding refactor propagates automatically.
- **`Pushery\WireKit\WireKit::spinePadding(string $tier = 'lg'): string` — NEW public helper.** Returns the canonical Tailwind utility string for the named padding tier (`px-[var(--padding-wk-x-{tier})]`). Use directly in developer-authored Blade class strings to opt INTO spine participation with one helper call — protects against tier typos AND gives the drift-audit guard a single canonical detection target. Tier values mirror the `--padding-wk-x-*` token family.
- **`wirekit:doctor --tier={package|environment}` filter.** Run only the package-tier checks (asset / config / directive / Tailwind-source / token-alignment / Alpine-cleanup) OR only the environment-tier checks (currently just compiled-view freshness). Default behaviour unchanged — running without `--tier` walks every check. Useful for interactive dev sessions where the developer cares about only one tier's signal.
- **`wirekit:doctor` compiled-views-freshness check.** Detects when `resources/views/` mtimes exceed `storage/framework/views/` by ≥60 seconds (the canonical "your test asserts a new prop but the assertion fails" failure mode caused by Laravel's view-cache lag). Emits a WARN with the actionable `php artisan view:clear` fix, short-circuits the diagnostic chain to the real cause. Honours the 60-second buffer to avoid false-positives on fast file-edit cycles. Categorised under the new `environment` tier.
- **`wirekit:export-api-map` new `css-classes` group.** Machine-readable enumeration of every public `wk-*` class — same inventory as `docs/extending/public-css-api.md` table, one entry per class with `tier` + `docs_url`. AI tooling consumes via `php artisan wirekit:export-api-map --pretty | jq '.groups[] | select(.id == "css-classes")'` for autocomplete / type-checker awareness.
- **`--wk-stagger-step` token default in `:root {}`.** The CSS variable now ships with a `75ms` default in `dist/wirekit.css` (previously developers needed to set it via the prop OR risk a silent `0ms` cascade). Documented in `docs/theming.md` under the new "Motion tokens" subsection alongside the existing `--motion-wk-*` token family.
- **`Marketing` category in `Pushery\WireKit\ComponentRegistry`.** Four conversion-focused primitives (`cta`, `feature`, `feature-grid`, `hero`) migrate from category `Display` to category `Marketing`. `footer` stays `Layout` (structural primitive), `brand-bar` stays `Navigation` (header chrome), `reveal` stays `Display` (generic animation). `wirekit:list --category=Marketing` now exits 0 and prints the four primitives; the canonical category enum becomes eight values (`Form`, `Layout`, `Typography`, `Navigation`, `Overlay`, `Display`, `Marketing`, `System`). See [`/extending/component-registry#categories`](/extending/component-registry#categories) for the per-category definition + usage criteria.
- **`Pushery\WireKit\Theming\ThemePresetRegistry`** — public single source of truth for every theme preset. `keys()` / `get(string $key)` / `all()` / `isValid(string $key)` / `isDefault(string $key)` cover read access; `register(string $key, array $preset)` lets downstream packages add custom presets at runtime from a service-provider boot hook. `wirekit:theme`, `wirekit:install --preset=`, and `wirekit:export-api-map themesGroup()` all read from this class — drift between their lists is impossible.
- **`Pushery\WireKit\Support\SuggestSimilar`** — public Levenshtein helper underpinning every CLI "Did you mean?" hint AND the runtime `WireKit::validateProp()` suggestion line. `byLevenshtein(string $needle, array $haystack, int $max = 3, int $maxDistance = 3)` returns up to N ranked candidates; `byLevenshteinScored(...)` exposes the distance scores for ranked-weighting developers (MCP servers, recently-used-bias suggestions); `format(array $suggestions)` pretty-prints the result. Cross-cutting use across `wirekit:show`, `wirekit:theme`, `wirekit:list`, `wirekit:publish-icons`, `wirekit:component`, `wirekit:install`, plus `WireKit::validateProp()`.
- **`wirekit:list --category=Marketing,Display` multi-category filter.** Comma-separated list returns the union of the per-category sets. Useful for "show me everything that could go on a landing page" queries.
- **`wirekit:list --format=...` alias for `--as=...`.** Matches the `--format=json` convention used by other Laravel commands. Both flags accept the same value set (`count|slugs|categories|json`) and resolve to the same code path; passing both simultaneously with different values is rejected.
- **`wirekit:component --interactive` flag.** Forces the `--base` chooser prompt even when TTY detection misfires (Herd / Docker / WSL). Pairs with the new derivation chain so `wirekit:component customer-dashboard` walks: right-segment strip → Levenshtein suggestion → interactive choice() prompt → fail-fast with actionable error when no candidate matches.
- **`<x-wirekit::brand-bar>` `container` + `max` props.** When `container=true`, the brand-bar's CHROME (background, border, sticky behaviour) stays edge-to-edge while CONTENT (brand, tagline, actions) aligns with the body's container-wrapped column via the inner max-width wrapper. `max` accepts the six-value container enum (`sm/md/lg/xl/2xl/full`), default `xl`. Reads `--size-wk-container-*` tokens so brand-bar + body align on the same vertical content-edge spine. Default `container=false` preserves the v2.0.0 edge-to-edge render.
- **`<x-wirekit::navbar>` `container` + `max` props.** Same shape as brand-bar's pair — when `container=true`, the navbar's outer `<nav>` (background, border, sticky behaviour) stays viewport-edge-to-viewport-edge while the inner flex row (brand, nav items, actions) AND the mobile-menu panel wrap to `max-w-[var(--size-wk-container-{tier})]` + `mx-auto`. `max` accepts `sm/md/lg/xl/2xl/full`, defaults to `xl`. Lets a marketing-landing-page navbar align its brand + nav items on the same content-edge spine as the body below without losing the edge-to-edge chrome. Default `container=false` preserves the v2.0.0 full-bleed content render.
- **`<x-wirekit::hero>` `size` prop + responsive vertical padding.** `size="sm|md|lg"` (default `lg`) names the `sm+` tier of `--space-wk-section-*`; the mobile viewport (< sm breakpoint) automatically drops one tier so the section never overshoots a small viewport. Closes the historical "14 rem vertical padding on every viewport" footgun without breaking the default `lg` semantic on tablet and up.
- **`<x-wirekit::hero>` gradient overlay containment.** When `gradient` is set, the overlay is now anchored INSIDE the content wrapper (max-w-xl) instead of as a direct child of the `<section>`. The previous shape filled the entire section including the vertical-padding band, producing a visible-empty-dark-area below content on mobile under `variant="dark"` + `gradient`. The overlay carries `pointer-events-none` so it never intercepts clicks.
- **`<x-wirekit::cta>` `size` prop.** Same shape as hero's `size` — three-tier responsive vertical padding (default `md`). Mobile drops one tier automatically.
- **`<x-wirekit::footer>` `max` prop.** Replaces the prior hardcoded `xl` inner-content max-width. Same six-value enum as brand-bar / container. All three inner wrappers (columns grid, brand+legal row, default slot) read from a single shared value — set `max` once and every wrapper updates. Default `xl`. Hardcoded fallback values in `var(--token, fallback)` pairs are removed (per user direction "kein hardcoded") — the tokens ARE the source of truth.
- **Two new documentation pages** under the "Extending" + "Recipes" sidebar groups: [`Composition Patterns — Chrome vs. Content`](/extending/composition-patterns) (canonical design contract explainer for the chrome-vs-content composition axis across every page-edge primitive) and [`Marketing Landing Page`](/recipes/marketing-landing-page) (copy-paste-ready worked example composing brand-bar + hero + feature-grid + stats + cta + footer with full visual alignment on the same content-edge spine).
- **`wirekit:install --diff` dry-run mode.** Reports what WOULD change in `resources/css/app.css`, the layout file, `config/wirekit.php`, and `public/vendor/wirekit/` without writing any files. Read-only preview. Exits 0 after rendering. Use to preview an install before committing — pairs with code-review workflows.
- **`wirekit:install --rollback` mode.** Reverses the most-recent install session by replaying recorded before-snapshots from `.wirekit-install.log` at the developer project root. Per-file restore. Returns 0 on full success, 1 on partial restore. Mutually exclusive with every other install flag.
- **`wirekit:install --no-strict` opt-out flag.** Pre-flight warnings print but do not abort. Use only for legacy CI scripts that depended on the v2.0.0 warnings-as-success semantic. Mutually exclusive with `--force`.
- **`wirekit:install --force` flag.** Bypasses pre-flight warnings (token clobber, hand-edited marker blocks). Errors still abort. Use only when you understand the consequences. Mutually exclusive with `--no-strict`.
- **`wirekit:install --ignore-failed-flags` flag.** Per-flag failures report but do not abort the install. Other flags still apply. Exit code reflects partial failure (non-zero so CI still detects it). Requires `--no-strict` (since strict-default would abort first).
- **`.wirekit-install.log` written by `wirekit:install` on success.** Append-only JSON-Lines audit trail at the developer project root. Each tracked side-effect records its file path + before-snapshot. Enables `--rollback`. Failed installs discard pending entries (don't pollute the log with partial state).
- **Token-clobber scan** during pre-flight validation. When a `--font*` flag is set AND `resources/css/app.css` already declares the matching `--font-wk-{category}` token OUTSIDE the wirekit marker block, the install aborts (or warns under `--no-strict`/`--force`) with an actionable error message naming the conflicting declaration's line + value. Closes a long-standing silent-overwrite footgun where developer-set tokens lost cleanly to wirekit's marker block on cascade order.
- **Pre-flight validation pass** in `wirekit:install`. Every flag is validated BEFORE any side-effect runs. Errors are aggregated in one pass — the user sees the full picture in one round-trip, not one error at a time. Filesystem is byte-stable across a failed validation. The pattern is codified for adoption by other multi-flag artisan commands in future plans.
- **CI / Deploy script discipline** section in `docs/integration.md` — canonical fail-fast install shape, exit-code reference, GitHub Actions example, legacy opt-out recipe, dry-run preview pattern, rollback workflow.
- **`wirekit:doctor` cleanup-hygiene check for custom Alpine plugins.** Static-analysis scan of the developer's `resources/js/` tree that flags two anti-patterns: an `IntersectionObserver` / `MutationObserver` / `ResizeObserver` instantiation without a `destroy()` cleanup hook, and a `disconnect()` call inside an observer callback without a preceding null-guard against post-teardown fire. Heuristic-based — emits a soft warning with a docs cross-link and respects a `// wirekit-doctor: cleanup-ok` opt-out comment for intentional patterns the heuristic doesn't recognise.
- **Three new documentation pages** under the "Extending" sidebar group: [`Authoring Custom Alpine Plugins`](docs/extending/authoring-custom-alpine-plugins.md) (defensive-cleanup pattern + worked example for developer-authored Alpine plugins), [`Pest Browser-Test Setup`](docs/extending/pest-browser-setup.md) (recipe for wiring `pestphp/pest-plugin-browser` into a WireKit-using Laravel app, including known-issue workarounds), and [`Console Output Baseline`](docs/extending/console-output-baseline.md) (catalog of what a clean WireKit install should emit on first paint, during interaction, and from optional-dependency warnings — helps developers triage unexpected output).
- **`Pushery\WireKit\Support\BladeParser`** — companion to `PropsParser` for the broader Blade-content surface (named slots, directive enumeration, comment extraction, component-reference discovery). Replaces the inline slot-detection regex previously embedded in `ExportJsonCommand`.
- **`wirekit:list --as=count|json|slugs|categories`** flag emits machine-readable output for script + AI consumption. `--as=count` produces a single integer with no decoration (`WK_COUNT=$(php artisan wirekit:list --as=count)`), `--as=slugs` newline-separated component names, `--as=categories` a JSON object mapping each category to its component count, `--as=json` the full per-component array with name + tag + category + description. Default human-readable output unchanged.
- **`wirekit:show <name> --as=json`** flag emits the per-component structured schema as JSON to stdout (no decoration). Includes `name`, `tag`, `category`, `description`, `docs_url`, `props[]` (full prop records with `default`, `default_normalized`, `comment`), and `sub_components[]`. Tooling that needs one component's schema without loading the full export-json output.
- **`wirekit:show <name> --validate-against=<path>`** flag lints a developer Blade file against the component's known prop set. Walks every `<x-wirekit::name ...>` usage, warns on unknown attributes with the closest matching prop name (Levenshtein-ranked) so typos like `intnet` → `intent` surface immediately. Pre-runtime catch of "did I typo a prop?". Exits 1 on any unknown attribute — wire into pre-commit hooks for prop-spelling enforcement.
- **`.wirekit-schema.json` written by `wirekit:install`.** Drop-in IDE-extension + AI-tool feeder file at the developer's project root. Contains the same JSON manifest as `wirekit:export-json --pretty` (every component's name + tag + category + description + full prop records + slots). Re-running `wirekit:install` regenerates the file — checked into git for stable autocomplete across teammates, or gitignored to keep installs fresh. Non-fatal on write-failure (read-only filesystem etc.).
- **Two new documentation pages** under the "Extending" sidebar group: [`ComponentRegistry — Programmatic Discovery`](docs/extending/component-registry.md) (canonical PHP-API for reading the component catalogue at runtime) and [`Authoring Custom Blade Components`](docs/extending/authoring-custom-components.md) (eight tested `@props` syntax shapes WireKit's tooling understands, plus CLI tooling for custom components).

### Changed

- **`wirekit:install` is now strict-by-default.** Invalid font keys, category mismatches, unknown preset names, invalid `--apex-license` tiers, AND detected token-clobbers all abort with exit 2 (INVALID) before any filesystem mutation. **v2.0.0 BEHAVIOUR CHANGE.** Previously these conditions printed a warning to stderr and the install continued with exit 0 — a silent failure mode that masked install bugs from CI. To preserve the legacy semantic, combine `--no-strict` (lets pre-flight warnings pass) + `--ignore-failed-flags` (lets per-flag exceptions pass).
- **`wirekit:install` exit codes are now precise:** `0` = success, `1` = runtime failure mid-install (verify failed, theme application failed, partial rollback), `2` = pre-flight validation rejected the input (no filesystem mutations happened). Wire these into CI as documented in [docs/integration.md "CI / Deploy script discipline"](docs/integration.md#ci--deploy-script-discipline).
- **Four components recategorised from `Display` to `Marketing`.** `cta`, `feature`, `feature-grid`, and `hero` carry their conversion-funnel intent in the category field now. **API-VISIBLE for developers reading `wirekit:export-json` / `wirekit:list --as=json` / `Pushery\WireKit\ComponentRegistry::all()`** — the four entries' `category` field changed value. Rendering, props, slots, and tags unchanged; no other API surface affected.
- **`wirekit:doctor` is now a Symfony alias of `wirekit:verify`.** Both names resolve to the SAME command instance — running either walks the identical check pipeline. `php artisan list wirekit` still shows two rows (the alias appears with an `[wirekit:doctor]` annotation on the verify row), but the standalone `DoctorCommand` class file is removed and both invocations share one underlying handler. **Internal API change** for developers who imported `Pushery\WireKit\Console\DoctorCommand` directly in a custom service provider's command-registration array — switch to `VerifyInstallationCommand` (which now declares `wirekit:doctor` via `setAliases()`).
- **`Pushery\WireKit\ComponentRegistry::extractProps()` return shape.** Was `array<string, string>` (flat name → default-string map). Now returns the structured `list<array{name, default, default_normalized, type_hint, comment}>` shape produced by `PropsParser`. **BREAKING for developers of the prior shape.** Migration: iterate the returned list, read each entry's `name` / `default` keys, and the inline-comment metadata (previously hidden) is now available via the `comment` field. No back-compat shim — the structured shape is the canonical surface going forward.

### Fixed

- **Silent exit-code lie in `wirekit:install --font=<invalid-key>`.** Previously the catch-block printed `✗ Unknown font key '...'` to stderr but did NOT propagate a non-zero exit code; the install continued through publishing / theming / verification and returned `self::SUCCESS`. CI scripts wrapped in `set -e` saw exit 0 and continued. Now pre-flight aborts immediately with exit 2 and the actionable error message.
- **`wirekit:theme default` returned FAILURE.** The `default` preset wasn't in `ThemeCommand`'s inline PRESETS array, so running `wirekit:theme default` exited 1 with "Unknown preset" — despite `wirekit:install --preset=default` being a valid no-op. Symmetry restored: `wirekit:theme default` now exits 0, actively removes any existing `wirekit:theme start/end` block from `app.css` (returning to the bundled token values), and emits an actionable status line either way.
- **`wirekit:show buttn` (and every other "Unknown X" error) had unreliable suggestions.** The historical `str_contains` bidirectional match in `ShowComponentCommand` produced suggestions only when the typo's characters were a substring of a real name (or vice versa). Single-char typos like `buttn` (distance 1 from `button`) never matched. The new `Pushery\WireKit\Support\SuggestSimilar::byLevenshtein` covers every "Unknown X" report uniformly across `wirekit:show`, `wirekit:theme`, `wirekit:list`, `wirekit:publish-icons`, `wirekit:component`, AND `WireKit::validateProp()` runtime messages.
- **`wirekit:component <name>` defaulted `--base` to `<name>` itself.** When `--base` wasn't passed, the default pointed at the new component's own (non-existent) source — every invocation without explicit `--base` failed with "Unknown base component". The new derivation chain (right-segment strip → Levenshtein fallback → interactive prompt → fail-fast with actionable error) handles the common `my-X → X` pattern automatically (e.g. `wirekit:component my-button` derives `--base=button` and copies `button.blade.php`).
- **Two regex-based `@props([...])` parsers consolidated into a single tokenizer-backed `Pushery\WireKit\Support\PropsParser`.** The historical parsers (`ComponentRegistry::extractProps` and `Console\ExportJsonCommand::extractProps`) silently truncated prop defaults that contained commas inside function-call argument lists (`config('x.y', null)` was split into two phantom props) AND leaked trailing `// comment` blocks into the captured default value. Both bug classes broke `wirekit:show` output AND the `wirekit:export-json` manifest — every developer agent (Cursor / Claude Code / MCP servers) reading the export got incomplete prop catalogues. The replacement uses PHP's own `token_get_all()` tokenizer and handles every legitimate `@props` shape: `config(...)` defaults, multi-line array literals, `match(...)` expressions, mixed-quote keys, heredoc / nowdoc values. A drift-audit guard (`tests/Feature/PropsParserCallerDriftTest.php`) blocks new regex-based `@props` parsers from being added to `src/`.
- **Unguarded observer callbacks in `wirekitStatAnimate` and `wirekitAnimate` Alpine plugins.** Browser-queued `IntersectionObserver` callbacks could fire AFTER Alpine teardown set `this._observer = null` (Livewire morph removing the host element pre-intersection is the canonical trigger), causing `TypeError: Cannot read properties of null (reading 'disconnect')` to log to the console. The page rendered correctly, but the silent error reded every developer's `assertNoSmoke()` / `assertNoJavascriptErrors()` browser-test against any WireKit page using `<x-wirekit::stat animate>` or `<x-wirekit::reveal>`. The fix adds a null-guard immediately before the in-callback `disconnect()` call in both plugins plus the `reading-minimap` initialisation `IntersectionObserver` that shared the bug shape. Sweep audit of the remaining observer instantiations across `reading-spine`, `reading-toc`, `reading-minimap` (4 sites), `chart`, and `chart-apex` confirmed they don't access the observer reference inside the callback, so no race exists.

## [2.0.0] — 2026-05-18

**Major release.** First publicly-cut tag after v1.6.3, aggregating every change since. Two breaking changes (both in `### Changed`): custom `Pushery\WireKit\Contracts\ChartAdapter` implementations must add three new methods to satisfy the expanded interface, AND `<x-wirekit::container>` inline-padding tiers now read from the `--padding-wk-x-*` token family instead of `--space-wk-*` so a container nested in `<x-wirekit::main>` inherits the same content-edge spine. Developers using only the built-in `ChartJsAdapter` or the new `ApexChartsAdapter`, and developers using `<x-wirekit::container>` with default padding values, see no behavioural change beyond a 0.5-rem horizontal alignment correction. Every other change is additive and back-compatible.

### Added

- **Optional ApexCharts chart adapter alongside the existing Chart.js adapter.** Switch with one line in `config/wirekit.php`: `'charts' => ['library' => 'apexcharts']`. Same `<x-wirekit::chart>` Blade tag for both libraries; zero breaking change. ApexCharts unlocks 9 chart types Chart.js does not ship natively — candlestick, boxplot, range-bar, range-area, heatmap, treemap, funnel, radial-bar, sparkline. Dedicated `dist/wirekit-apex.js` adapter bundle (~2 KB gzip glue, no ApexCharts library code; the developer installs `apexcharts` via npm). **Note: ApexCharts is not MIT-licensed — the free Community License covers organisations under $2M USD annual revenue; Commercial License required above. WireKit ships only the adapter glue (MIT). See [docs/components/chart.md#license-apexcharts-only](docs/components/chart.md) for the full terms.**
- **`<x-wirekit::sparkline>` — NEW first-class component.** Inline trend sparkline (axis-less line chart) for KPI strips and dashboard cells. Six props (`data` / `trend` / `inline` / `height` / `scope`). Auto-detects trend from first-vs-last data and tints green / red / muted accordingly; manual `trend="up|down|neutral"` override available. Inline mode renders at surrounding text height (1.25em / 4rem width); block mode at configurable height (default 2.5rem). Delegates to `<x-wirekit::chart type="sparkline">` so both adapters render correctly — ApexCharts uses native sparkline mode, Chart.js falls back to a plain line.
- **`<x-wirekit::chart-mixed>` — NEW component for multi-axis dashboards.** Each dataset declares its own `type` (line / bar / column / area) plus an optional `yAxisID` for multi-axis configurations. Both adapters consume the per-dataset type field natively — Chart.js via the per-dataset `type` field, ApexCharts via `series[].type`.
- **`wireStream` prop on `<x-wirekit::chart>` for real-time data updates.** Subscribe a chart to a Livewire-emitted event and append each fired payload via the library's imperative API (`chart.update('none')` for Chart.js / `chart.appendData()` for ApexCharts). `wireStreamMode="strict"` (default) FIFO-trims at `wireStreamCap` points (default 100); `wireStreamMode="stream"` grows unbounded.
- **`annotations` prop on `<x-wirekit::chart>` for vertical lines / horizontal regions / point callouts.** ApexCharts has annotations built-in; Chart.js requires `chartjs-plugin-annotation` (the Alpine factory emits a console.warn when annotations are supplied but the plugin is missing — graceful degradation).
- **Smooth dark-mode preset transitions on chart re-theming** (~250 ms ease-out). Both adapters interpolate colours during `.dark` toggle instead of snapping. Collapsed to instant under `prefers-reduced-motion: reduce`.
- **Two new chart-system docs subhierarchies on docs.wirekit.app:** `/components/charts-chartjs/` (full Chart.js demo set — bar / line / area / pie-doughnut / scatter-bubble / radar-polar / advanced / theming) and `/components/charts-apex/` (full ApexCharts demo set — line / area / bar / column / range-bar / pie-donut / radial-bar / radar / scatter-bubble / heatmap / treemap / candlestick / boxplot / funnel / timeline / sparklines / annotations / streaming / mixed / motion / theming). Every page ships realistic seed data drawn from B2B SaaS, e-commerce, DevOps, finance, marketing domains.
- **`wirekit:install --apex-license=community|commercial|oem` flag.** Records the developer's license tier into `config/wirekit.php` `charts.apex_license` after printing the License Notice once at install time. Suppresses the `wirekit:doctor` reminder for `commercial` and `oem` tiers.
- **`wirekit:doctor` ApexCharts checks.** Detects `apexcharts` npm package presence, validates the active license tier, confirms `dist/wirekit-apex.js` is published.
- **`wirekit-apex.js` registered as publishable** under both the `wirekit-scripts` and `wirekit-assets` tags. Running `php artisan vendor:publish --tag=wirekit-assets` now copies the adapter glue to `public/vendor/wirekit/wirekit-apex.js` alongside the other JS bundles. The route fallback at `/wirekit/wirekit-apex.js` also works for setups that prefer route-based serving over publishing. Eliminates the manual `cp vendor/.../dist/wirekit-apex.js public/vendor/wirekit/` step that ApexCharts adopters previously had to run after every `composer update`.
- **`<x-wirekit::reading-toc>` — NEW primitive in the reading family.** Horizontal sticky-strip TOC sibling to `reading-spine`. Same auto-build-from-headings + IntersectionObserver active-section model, different rendered shape (a flat row of links across the top or bottom of the article container). Use case: marketing landing pages with 3-4 anchored sections (Hero, Features, Pricing, FAQ) where a vertical sidebar feels excessive. Seven props (`target`, `levels`, `position`, `offset`, `hideBelow`, `flush`, `scope`). Defaults to `levels="2"` for landing-page flat structure; mobile-hidden via `hideBelow="sm"` since narrow viewports cannot host a horizontal strip without overflow. The `flush` prop zeroes the first link's left-edge padding and the last link's right-edge padding so the visible text aligns flush with the strip's content edges (use when the TOC sits directly under `<x-wirekit::brand-bar>` or `<x-wirekit::main>` and the developer wants the first link on the same vertical content-edge spine as the surrounding brand text and h2 headings). Real `<a href="#section-id">` links inside a `<nav aria-label="Page sections">` landmark — keyboard-navigable + screen-reader native. `prefers-reduced-motion: reduce` collapses smooth-scroll to instant. Reuses spine's `--reading-spine-color-idle` / `-active` for theme consistency; adds 5 layout-specific tokens (`--reading-toc-bg` / `-padding-y` / `-padding-x` / `-gap` / `-link-max-width`) plus 2 colour aliases.
- **`<x-wirekit::brand-bar>` — NEW navigation primitive.** Page-chrome wrapper for the canonical "logo + tagline + actions" header pattern. Three named slots: `brand` (typically a `<x-wirekit::brand>` primitive), `tagline` (secondary muted text), `actions` (right-anchored sign-in / theme-toggle / account-widget content via `margin-left: auto`). Props: `as` (`header` | `nav`), `divider` (`bottom` | `none`), `padding` (`none`/`sm`/`md`/`lg`/`xl`), `sticky` (pins to `top: 0` during scroll with an opaque background fallback). Carries the content-edge spine via `padding="lg"` reading from `--padding-wk-x-lg`, so the brand visible-text aligns with `<x-wirekit::main padding="lg">`, `<x-wirekit::reading-toc flush>`, and the article h2 headings below on the same vertical X-coordinate — out of the box, no custom CSS.
- **`<x-wirekit::prose :density>` — NEW prop on the existing component.** `comfortable` (default) keeps the long-form-article heading scale (h2 mt = 2.5 rem for generous section breaks, h1 = 1.875 rem). `compact` tightens for marketing pages (h2 mt = 0.75 rem, h1 one type-scale tier smaller, p mb = 0.5 rem). Eliminates the need to override prose typography with developer-side `.my-section h2 { margin: ... }` rules when the prose lives inside a tight marketing layout. Default `comfortable` keeps backward-compatible rendering.
- **`<x-wirekit::reading-shell :toc="true">` — opt-in toc toggle.** Defaults to `false` in every density preset (comfortable / compact / minimal). Marketing landing pages explicitly opt in via `:toc="true" :spine="false"`; blog posts and docs pages keep the spine sidebar.
- **Marketing Landing TOC recipe** at `/recipes/marketing-landing-toc` — sibling to long-form-article. Demonstrates the canonical Hero / Features / Pricing / FAQ landing-page pattern with the new sticky TOC strip, plus the `offset` prop pattern for layouts with a fixed nav above the strip.
- **Long-form Article recipe** at `/recipes/long-form-article` — canonical Medium / Substack-style article layout: reading-progress bar at the top, reading-spine sidebar on the right, reading-bookmark resume pill, reading-meta time-to-read. Demonstrates the full reading-* family in one composition, both via the `<x-wirekit::reading-shell>` sugar wrapper and via direct primitive composition for power-user customisation.
- **Documentation Reader recipe** at `/recipes/documentation-reader` — Stripe / Tailwind-docs-style article shell with reading-progress bar, dense-section reading-spine, fixed-nav offset on the TOC strip, reading-bookmark across multi-page-session reads. Shows the docs-rhythm pattern (heading-scale + dense per-paragraph code blocks) alongside the same reading-* primitives.
- **`<x-wirekit::reading-minimap>` — NEW primitive in the reading family.** Every-item density overview of a scrollable container, with two rendering modes:
  - `mode="stripes"` (default) — every item matched by `itemSelector` renders as a 1–2 px stripe at proportional vertical position. `itemStyle="block"` upgrades to per-paragraph rectangles whose height tracks the source item's natural height (skeleton-style content texture).
  - `mode="rendered"` — PhpStorm / VS Code-style abstract content canvas. Walks every text-bearing node via `TreeWalker`, gets per-rendered-line rects via `Range.getClientRects()`, draws each as a rectangle on a DPR-aware canvas. Per-element-type colour palette (h1–h6 each its own alpha tier, code indigo, table rose, blockquote slate-bolder, image amber, WireKit `wk-*` components emerald, prose / default slate-muted). 13 `--reading-minimap-color-{h1..h6,code,table,blockquote,prose,wirekit,image,default}` tokens for full re-theming without JS.
  - Translucent viewport-overlay rectangle tracks the host's visible region; click stripe → smooth-scroll target so item is centered (instant under `prefers-reduced-motion: reduce`); drag overlay → pan host scroll position with browser-scrollbar-matched translation; hover stripe (non-touch) → tooltip following the cursor with the item label.
  - Two canonical use cases: long-form article density-overview (sibling to `reading-spine`) and sidebar navigation density-overview.
  - 5000-tag silent fallback to stripe mode on very long articles. No DOM clone in rendered mode → no parse step → no surface for HTML-injection vectors.
- **`<x-wirekit::reading-meta perParagraph>` — Medium-style inline annotations.** Opt-in mode that injects small `<span class="wk-reading-meta-paragraph">N min</span>` annotations immediately before each `<p>` in the target with at least `paragraphMinWords` words (default 30). Annotations show estimated remaining-time FROM that paragraph onward, re-computed on scroll. `aria-hidden="true"` on every annotation — canonical SR text remains the total/remaining display. Default off; opt-in.
- **`<x-wirekit::reading-progress variant="auto">` — theme-reactive fill mode.** Falls back to `currentColor` when the developer hasn't set `--reading-progress-fill` — useful for embedded contexts (iframes, browser extensions) where the bar should match the surrounding text colour. Joins the canonical 6-value variant set (`primary | neutral | success | warning | danger | info`).
- **`<x-wirekit::reading-shell density>` preset prop.** Three values (`comfortable` | `compact` | `minimal`) adjust the shell's per-primitive defaults: progress-bar height, spine expand mode, which primitives render by default. Per-primitive toggles (`:spine="false"` etc.) win over the density preset.
- **14 new design tokens for the reading-* family**: 1 on `reading-progress` (`--reading-progress-fill`), 7 on the new `reading-minimap` (width, stripe-height, stripe-gap, color-idle, color-active, viewport-bg, viewport-border), 2 on `reading-meta` perParagraph mode (paragraph-color, paragraph-spacing). All themeable in `:root {}`.
- **`<x-wirekit::stat>` composes `animate` (counter count-up) with `animateIn` (entrance reveal).** The Blade template emits an outer wrapper that carries the entrance reveal while the inner element keeps the counter handler — the two scopes layer cleanly. Single-flag usages render byte-identical.
- **`<x-wirekit::cta>` and `<x-wirekit::footer>` accept the `animateIn` prop.** Both marketing primitives now match the existing `animateIn` surface on `<x-wirekit::card>`, `<x-wirekit::feature>`, `<x-wirekit::hero>`, and others. Passing `animateIn="slide-up"` (or any of the 11 base presets) wires `<x-wirekit::reveal>` semantics inline without an extra wrapper element. Honours `prefers-reduced-motion: reduce`.
- **`stagger` prop on `<x-wirekit::feature-grid>` and `<x-wirekit::stats>`.** When set on the wrapper and an entrance preset is configured on the children, each child's animation fires with an incremental delay — produces a clean cascade instead of every card landing at once. Boolean form uses a 75ms step (`stagger`); integer form overrides for custom rhythm (`:stagger="125"`). Pure CSS via `:nth-child` rules with an index cap at 8 to bound delay on long lists; collapses to 0 under reduced motion. Zero JS bundle impact.
- **`delay` prop on `<x-wirekit::reveal>`.** Holds the entrance for a beat before it begins — useful for sequencing cards or letting a hero-section heading land before the supporting copy follows. Accepts five named tokens (`none`, `sm`, `md`, `lg`, `xl`) mapping to themeable CSS variables, or a raw integer in milliseconds for one-offs. Composed via inline `animation-delay` on the existing `wk-animate-{preset}` class — no JS plugin change needed. Collapses to `0ms` under `prefers-reduced-motion: reduce`.
- **Five new motion-delay tokens in `dist/wirekit.css`:** `--motion-wk-delay-none` (`0ms`), `--motion-wk-delay-sm` (`75ms`), `--motion-wk-delay-md` (`150ms`), `--motion-wk-delay-lg` (`300ms`), `--motion-wk-delay-xl` (`500ms`). All developer-overridable in `:root {}`.
- **CSS class `.wk-stagger`** plus eight `:nth-child` rules and a cap-at-8 index ceiling. Drives the `stagger` prop on feature-grid and stats. Reduced-motion override zeroes per-child delays inside the existing `@media (prefers-reduced-motion: reduce)` block.
- **`wirekit:doctor` thirteenth check — `:root` vs `.dark` colour-token symmetry.** The doctor now reads the developer's `resources/css/app.css`, extracts the `:root {}` and `.dark {}` blocks, and emits a warning when `--color-wk-*` tokens are declared in one block but missing from the other. Asymmetric colour tokens produce theme drift the developer sees as "looks wrong in dark mode" without an obvious cause.
- **`<x-wirekit::reading-progress>`** — viewport-pinned reading-progress indicator for long-form articles. Bar (default) or dot (`indicator="dot"`) variants; both fill 0 → 100% on scroll using compositor-only properties (`transform: scaleX` / `stroke-dasharray`). Five `variant` colours, three `height` tokens (`sm`/2px, `md`/3px, `lg`/5px), `showAfter` scroll threshold, `target=` selector, `segments` chapter markers, `milestones` events (`wirekit:reading-progress:milestone` Alpine dispatch fired ONCE per session at 25 / 50 / 75 / 100% boundaries). `role="progressbar"` + dynamic `aria-valuenow`. Zero-KB bundle impact.
- **`<x-wirekit::reading-spine>`** — sidebar mini-TOC that auto-builds from page headings. Pins to the right edge at md+ breakpoints; tracks scroll position via `IntersectionObserver`; expands on hover or focus. Configurable `target=`, `levels=`, `position=`, `expand=` (`hover` / `focus` / `always` / `always-md`), `offset=`. Real anchor links so navigation works without JS. Five opt-in extensions: `numbered`, `fillSections`, `backToTop`, `expand="always-md"`, and a `wirekit:reading-spine:section-changed` event. Filter slot composition pattern. Bundle impact: ~600 bytes gzip.
- **`<x-wirekit::reading-bookmark>`** — persists scroll position to `localStorage` while reading; surfaces a "Resume reading where you left off?" pill on return-visit when conditions are met (previous-session dwell time ≥ `minDwellSeconds`, scroll moved past `threshold * scrollHeight`). Cross-tab consistency via `storage` event. `try/catch` wrapping every storage op — silently degrades on private-browsing / quota-exceeded. Required `key` prop (typically `"article-{slug}"`).
- **`<x-wirekit::reading-meta>`** — small text element showing `~12 min read` (initial estimate) and optionally `~5 min remaining` (live-tracking on scroll). Skips non-prose nodes (`pre`, `code`, `figure`, `figcaption`, `img`, `picture`, `svg`, `[data-language]`). CJK-aware: when more than 40% of text is CJK ideographs, falls back to character-based estimation with a configurable `cjkCharsPerMinute` baseline (default 500). `wpm` clamps to `≥ 50`; `totalMinutes` floors to 1. `role="status" aria-live="polite"`.
- **`<x-wirekit::reading-shell>`** — composition wrapper that renders `<x-wirekit::reading-progress>` + `<x-wirekit::reading-spine>` + `<x-wirekit::reading-bookmark>` + `<x-wirekit::reading-minimap>` + `<x-wirekit::reading-meta>` around the slot content in one tag. Mirrors the `<x-wirekit::app-shell>` "one tag, full UX" pattern. Per-component opt-out via `:progress="false"` etc. Forwards every documented child prop via flat surface.
- **CSS variable tokens for the reading-* family in `dist/wirekit.css`:** three height tokens for the bar (`--reading-progress-height-{sm,md,lg}`), the dot diameter (`--reading-progress-dot-size`), seven spine layout / colour tokens, three bookmark pill tokens, two reading-meta tokens.
- **`@media print { display: none !important }`** rule for every reading-* primitive — only the article body prints, not the reading chrome.
- **`prefers-reduced-motion: reduce` gating for the entire reading-* family** in the global `@media (prefers-reduced-motion: reduce)` block.
- **`<x-wirekit::replay-button>` — NEW companion primitive.** Renders an icon-button that walks the closest `[data-replay-target]` ancestor and re-mounts it (`Alpine.initTree`) so its `x-data="wirekitAnimate(...)"` / counter / chart-motion fires again. Standardises the "re-play this animation" affordance every animation-capable component carries via the `data-replayable="true"` contract — developers can place the button explicitly anywhere alongside a primitive, or rely on docs.wirekit.app's preview chrome auto-injecting it. Two props: `label` (button `aria-label`, default `"Replay"`), `scope` (named personalization scope).
- **`replayable` prop on `<x-wirekit-chart>`.** Opt INTO the docs.wirekit.app `↻ Replay` button surface by emitting `data-replayable="true"` on the chart root. Set it explicitly when the entrance animation is worth re-watching (bar-grow / line-trace / slice-sweep on `/components/charts-apex/motion`), or rely on the auto-detect path: whenever `wireStream` is bound (every streaming chart on `/components/charts-apex/streaming`), the attribute is emitted automatically. Back-compat preserved: callers who already passed `data-replayable` raw via the attribute bag continue to work — the Blade view skips its own emission to avoid duplication.

### Changed

- **`Pushery\WireKit\Contracts\ChartAdapter` interface expanded from 4 → 7 methods.** Net-new: `name()` / `rendersTo()` / `supportedTypes()`. Existing `scripts()` / `normalizeData()` / `defaultOptions()` / `alpineComponent()` unchanged. Developers using only built-in adapters see no impact (both `ChartJsAdapter` and `ApexChartsAdapter` ship the new methods). Custom adapter implementations need to add the three new methods to satisfy the interface. **BREAKING for developers with a hand-written `ChartAdapter` implementation.**
- **`<x-wirekit::container>` inline-padding moved from `--space-wk-*` to `--padding-wk-x-*`.** `padding="sm|md|lg|xl"` now reads from the `--padding-wk-x-*` content-edge spine instead of the `--space-wk-*` block-rhythm scale. Resolves a silent 0.5 rem horizontal drift when nesting `<x-wirekit::container padding="lg">` inside `<x-wirekit::main padding="lg">` — both now share the same content-edge X-coordinate. Developers who depended on the wider previous value (e.g. `padding="lg"` was 1.5 rem, now 1 rem) should bump to `padding="xl"` (also 1.5 rem) for unchanged visual output. **BREAKING for developers relying on the exact pixel value of container's horizontal padding.**
- **`<x-wirekit::sidebar>` inline-padding fixed.** Was applying `--padding-wk-y-sm` (the y-axis token) on all four edges; now correctly splits into `px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]` and moves the inter-item `gap` to `--space-wk-sm` (the canonical gap token). Sidebar items at the inboard edge now align with `<x-wirekit::header>` and `<x-wirekit::main>` rendered alongside on the same vertical content-edge spine. Back-compat: visual change is a few-pixel adjustment to the sidebar's internal padding; no API change.
- **Color-prop unification across `<x-wirekit::message>`, `<x-wirekit::alert>`, `<x-wirekit::callout>`, and `<x-wirekit::progress>`.** Every component that exposes a color prop now accepts the same canonical six-value set (`primary, neutral, success, warning, danger, info`). `progress` adopts `primary` as the canonical name; the historical `accent` value continues to work as a back-compat alias. New [Variants & Intents](docs/variants.md) page documents the canonical set, the per-component prop tables, and the visual-synonym semantics in one place.
- **Bundle size**: `dist/wirekit.js` grew from 25 → 26 KB gzip (83 → 86 KB raw) covering the new reading-* family and chart-adapter expansion. Stays within the ±2 KB drift budget per `docs/dependencies.md`. Core bundle (chart-only) unchanged at ~2 KB gzip / 11 KB raw. ESM bundle ~25 KB gzip / 82 KB raw.
- **`.wk-reading-toc__list` inline-padding ships without `!important`.** Developers running a typography prose-wrapper (e.g. a `@tailwindcss/typography` body or any `body.prose`-style class) should carve out `[class*="wk-"]` from their `ul/ol/li` rules — same pattern they already use for the `max-width: 75ch` typography clamp. Without the carve-out the `padding-left: 1.75rem` in a typical prose stylesheet wins on specificity over `.wk-reading-toc__list { padding-inline: 0.25rem }` and pushes WireKit list-based components (reading-toc, list, breadcrumb) off the content-edge spine. Recommended typography rule shape: `.prose ul:not([class*="wk-"]), .prose ol:not([class*="wk-"]) { padding-left: 1.75rem }`.

### Documentation

- **`docs/integration.md` — new "Performance — Conditional Asset Loading" section** documenting a server-side flag pattern for emitting `highlight.js` and `Chart.js` `<script>` tags only on pages that actually use `<x-wirekit::code-block>` or `<x-wirekit::chart>`. Saves ~95 KB gzip on lightweight routes (landing pages, theming guide, recipes without code/charts) without breaking caching or first-paint correctness. Pattern is documentation-only; no helper class shipped — adapt the `str_contains()` detection to your DocsParser / class-naming conventions.
- **`/theming/design-tokens` — NEW comprehensive reference page** for every WireKit CSS variable: colors, typography, motion, sizing, component-specific tokens, and the chart-theming palette. Single landing page where developers can find any token by name before reaching for its component's docs page.
- **`/getting-started/livewire-starter-kit` — NEW recipe page** for fitting WireKit into an existing Livewire Starter Kit project. Walks the four touchpoints where Starter-Kit defaults differ from a clean-room `wirekit:install` (theme preset, font stack, dark-mode flip, sidebar conflict resolution) so existing Starter-Kit developers don't have to discover them ad-hoc.

---

## [1.6.3] — 2026-04-30

Patch release covering one developer-facing dependency fix, one CI build-stability fix, and a README link-table cleanup. No new API surface; no breaking changes.

### Fixed

- **`livewire/livewire` is now a direct require, not a require-dev.** Running `composer require pushery/wirekit` previously installed the package without pulling Livewire — developers who hadn't already added Livewire to their project then ran `php artisan wirekit:install` and hit confusing "class not found" errors at first component render. Livewire 4+ is a stated minimum requirement (per the README's stack badges and the integration guide); composer.json now reflects that contract and pulls Livewire into the developer's project automatically. Existing developers who already have Livewire in their `composer.json` see no change — composer will just keep their pinned version. Net effect: zero-step Livewire setup for new installs.

- **CI markdown lint stabilised against `markdownlint-cli2` minor-version drift.** The CI workflow runs `npx markdownlint-cli2` which always pulls the latest published version, while the local dev environment was pinned to `^0.21.0`. When `0.22.1` shipped a stricter `MD038` rule interpretation, CI started failing on a markdown file that the local tooling considered clean. Bumped the local pin to `^0.22.1` so local + CI run the same lint version, and fixed the lingering `MD038` violation surfaced by the new rule.

- **README documentation links no longer 404.** The `Components` badge and the "Documentation" table linked to `docs.wirekit.app/components` and `docs.wirekit.app/recipes`, neither of which existed as index routes — both returned 404. Replaced with links to working pages: the docs root (`docs.wirekit.app`), the getting-started guide, and the theming guide. Pointing readers at routes that resolve.

---

## [1.6.2] — 2026-04-30

Patch release covering three small surface-polish fixes spotted in the v1.6.1 README + docs walk. No new API surface; no breaking changes; every component renders byte-identical to v1.6.1.

### Changed

- **`README.md` — removed the "Tests" CI badge.** The badge image returned 404 and rendered as broken on every README view. May return in a future release if a green-build signal can be sourced cleanly; for now, removing the broken image is the honest choice.

### Fixed

- **`<x-wirekit::code-block>` copy-button cursor.** The toolbar copy button rendered without a `cursor: pointer` on hover, leaving keyboard / mouse users unsure whether it was clickable. Added `cursor-pointer` to the button class — hover state now signals interactivity unambiguously, matching every other interactive control in WireKit.

- **`docs/cli/wirekit-doctor.md` — "Common failures and fixes" section restructured.** The four subsection headings previously embedded the literal doctor command output (with `⚠` / `i` glyphs and inline-code backticks at H3 size), which rendered awkwardly on docs.wirekit.app — small icons forced to heading scale, monospace at heading weight. Each subsection now uses a descriptive title (e.g. "Sans font mismatch") and quotes the exact doctor line in a `text` code block in the body. Reads cleanly at every viewport.

---

## [1.6.1] — 2026-04-30

Patch release covering five developer-visible fixes plus a README restructure and badge expansion. No new API surface; no breaking changes; every component renders byte-identical to v1.6.0 unless its specific bug applied.

### Fixed

- **`prefers-reduced-motion: reduce` now actually honored on `<x-wirekit::reveal>` and `wk-animate-*` utilities (WCAG 2.3.3).** The component documentation and helper docblock claimed the OS-level reduced-motion preference was respected, but the global `@media (prefers-reduced-motion: reduce)` block in `dist/wirekit.css` only gated `[x-transition]` selectors — the 22 keyframe animations driving every preset ran at full speed regardless of user preference. The block now gates `[class*='wk-animate-']` as well, snapping `animation-duration` to `0.01ms` so reduced-motion users see an instant snap-to-final state. Behavior now matches the documented contract.

- **Nested `<x-wirekit::list>` margins no longer accumulate inside typography wrappers.** When the component is rendered inside a developer's `.prose` / `.docs-prose` / `@tailwindcss/typography` context, the wrapper's `<ol>` / `<ul>` / `<li>` rules injected `margin: 1em 0` onto every level — and the previous Tailwind-utility approach was too low-specificity to win the cascade. Nested lists looked progressively more spaced out at deeper levels regardless of the `spacing` prop. The component now ships with a `wk-list` marker class plus dedicated `wk-list-spacing-{none,sm,md}` rules in `dist/wirekit.css` whose doubled-class selectors win on specificity alone (no `!important`, so developers can still override with even-higher-specificity rules when they explicitly need to). The `spacing` prop is now the single source of truth for inter-item vertical rhythm at every nesting depth.

- **`bounce` and `spring` reveal presets — final state no longer fades back to invisible.** The `wk-bounce-in` and `wk-spring-in` keyframes declared `opacity: 1` only at the 50% / 60% peaks; the 70% and 100% frames left opacity unspecified. With `animation-fill-mode: both`, some browser implementations held opacity at 1 from the last specified frame, but others interpolated back toward the underlying inline `opacity: 0` after the animation settled — the element became visible briefly, then vanished. All four `bounce` / `spring` keyframe blocks now declare `opacity` at every frame, so the final-state behavior is browser-independent.

### Changed

- **`README.md` restructured from a comprehensive standalone reference to a concise getting-started overview.** Installation walkthrough, Quick Start example, and the browser-support table are retained on the GitHub landing page; extended component tables and the customisation reference now live at docs.wirekit.app where they're searchable and link-rich. Net effect: the README acts as a 30-second "what is this and how do I install it" landing page; docs.wirekit.app remains the authoritative reference for everything else.

- **`README.md` badge row expanded from 3 to 11 badges across two visual rows.** Row 1 (project status): Packagist version, Total Downloads, GitHub Actions test status, MIT licence, GitHub Stars, component count. Row 2 (stack): PHP ≥ 8.4, Laravel 12+, Livewire 4+, Tailwind CSS v4, Alpine.js. The CI badge in particular gives evaluators an at-a-glance signal that the build is green on `main`. Component-count badge auto-validates against `ComponentRegistry` via a Pest sync guard so the displayed number can never drift from the actual registry size.

---

## [1.6.0] — 2026-04-29

Minor release covering font-installation flags, doctor-command token diagnostics, three new opt-in props on `<x-wirekit::stat>`, a complete motion subsystem with the new `<x-wirekit::reveal>` component, and supporting CLI helpers. No breaking changes; every new flag, prop, and opt-in surface defaults to off so v1.5.0 developers see byte-identical output.

### Added

- **`wirekit:install --font=<sans-key>`, `--font-serif=<key>`, `--font-mono=<key>`** — three optional flags for choosing which bundled WireKit font your project uses. Resolves the key against the bundled `FontRegistry` (curated Google Fonts bundled locally for GDPR compliance), validates the category, and idempotently injects an override block into `resources/css/app.css` setting BOTH `--font-{cat}` (drives Tailwind `font-{cat}` utilities) AND `--font-wk-{cat}` (drives WireKit chrome). The two stay aligned automatically — closes the foot­gun where Tailwind utilities and WireKit chrome rendered different families. All three flags combinable; re-running with the same key produces byte-identical output. Wrong-category passes throw with a list of valid keys for the right category. Local fonts only — nothing is fetched from a CDN.

- **`wirekit:install` Tailwind config writer detection** — the install command detects whether your project uses CSS-first Tailwind v4 config (`@theme` block in `app.css`) or the legacy JS-config (`tailwind.config.js theme.extend.fontFamily`) and writes the font override to the right destination. CSS-first wins on tie (Tailwind v4 deprecates JS config). Custom config shapes that the auto-edit can't safely match log an actionable manual-edit hint instead of risking AST corruption.

- **`wirekit:install` interactive mode** — running the command without flags in an interactive TTY opens a guided setup prompting for theme preset + sans/serif/mono font selection. CI / `--no-interaction` / scripted contexts skip prompts and run with v1.5.0-identical defaults.

- **`wirekit:doctor` token-alignment diagnostic** — new section comparing seven Tailwind tokens against their matching WireKit tokens: `--font-sans/serif/mono`, `--color-accent`, `--color-accent-foreground`, `--radius`, `--shadow`. Each pair emits `✓ aligned`, `⚠ mismatch` (with actionable fix hint), or `i skipped` (var() reference or unset). Surfaces token drift at install-time rather than letting it ship to production.

- **`docs/cli/wirekit-doctor.md`** — dedicated documentation page for the doctor command. Covers every diagnostic check with example output, three "Common failures and fixes" walkthroughs (font mismatch, accent-colour mismatch, var() skip), and a GitHub Actions integration recipe.

- **`<x-wirekit::stat animate>` Three Description Options** — three opt-in props govern how the description text behaves during the value count-up animation. Option A `descriptionDeferred` defers the description fade-in until the counter settles (200ms ease-out), with `aria-hidden` mirroring visibility for screen-reader contract. Option B (default, status quo) renders the description statically. Option C `descriptionAnimate` animates the description text colour from `--color-wk-text-muted` → `--color-wk-text` synchronously with the value count-up. Options A and C are mutually exclusive — passing both throws. All three honour `prefers-reduced-motion: reduce`. New `### Animation Scope` subsection in `docs/components/stat.md` documents the contract.

- **`<x-wirekit::reveal>` component** — NEW thin Blade wrapper that animates its slot content into view. One `preset` prop selects from 11 bases × in/out variants (`fade`, `slide-up`, `slide-down`, `slide-left`, `slide-right`, `scale`, `zoom`, `flip`, `rotate`, `bounce`, `spring`). Three trigger modes: `viewport` (default, IntersectionObserver), `click`, `manual` (developer dispatches `wirekit:reveal` event). Three duration tokens: `fast` (150ms), `normal` (300ms, default), `slow` (600ms). Full `prefers-reduced-motion: reduce` honoring — element snaps to final state. New docs page `docs/components/reveal.md` with eight live preview blocks.

- **`docs/animations.md`** — NEW reference page covering the entire motion subsystem: six new design tokens, 22 keyframes (11 bases × in/out), 22 utility classes (`.wk-animate-{preset}-{in|out}` plus three duration modifiers `.wk-animate-{fast|normal|slow}`), the `wirekitAnimate` Alpine helper API, and the reduced-motion contract.

- **Six new motion design tokens** in `dist/wirekit.css`: `--motion-wk-duration-fast: 150ms`, `--motion-wk-duration-normal: 300ms`, `--motion-wk-duration-slow: 600ms`, `--motion-wk-easing-out` (decelerating cubic), `--motion-wk-easing-in` (accelerating cubic), `--motion-wk-easing-spring` (overshoot). The legacy `--transition-wk-duration` is aliased to `--motion-wk-duration-fast` for back-compat.

- **`animateIn` prop on 7 marketing components** — `<x-wirekit::card>`, `<x-wirekit::feature>`, `<x-wirekit::hero>`, `<x-wirekit::stat>`, `<x-wirekit::callout>`, `<x-wirekit::alert>`, and `<x-wirekit::empty-state>` accept an optional `animateIn` prop that wires the `wirekitAnimate` Alpine helper to the root. Pass a base name (`fade`, `slide-up`, `bounce`, …) or a full preset name (`fade-in`, `slide-up-out`). Default `null` preserves v1.5.0 render exactly. Components with built-in entrance transitions (`modal`, `drawer`, `toast`, `tooltip`, `dropdown`, `popover`) deliberately skip this prop to avoid double-motion conflicts.

- **`wirekit:export-api-map`** — new top-level `helpers` group covering Alpine helpers exposed by the WireKit JS bundle. `wirekitAnimate` lists its full 22-preset enum, three trigger modes, three duration tokens, reduced-motion contract, and Blade-wrapper hint. `wirekitStatAnimate` documents its reactive state catalog (`value`, `animating`, `progress`). The exported map gives editor tooling and IDE extensions a single source of truth for the correct `x-data="…"` shape without grepping the source.

- **`<x-wirekit::list>` four new ordered marker types** — `lower-roman` (i, ii, iii), `upper-roman` (I, II, III), `lower-alpha` (a, b, c), `upper-alpha` (A, B, C) join the existing `disc`, `decimal`, `none` set. All four render as `<ol>` with Tailwind v4 arbitrary-value `list-style-type` utilities. Mix freely across nested levels for legal-contract / academic / spec-style outlines.

### Changed

- **`<x-wirekit::main>` horizontal padding aligned with `<x-wirekit::header>`.** Previously `padding="lg"` produced 1.5rem all around (via `--space-wk-lg`); now uses `--padding-wk-x-{size}` for the horizontal axis (1rem at `lg`) — matching the same-name token used by Header. A sibling Header + Main pair (the canonical app-shell layout) now shares one vertical alignment line. Vertical padding stays on the generic `--space-wk-{size}` scale for breathing room. Applies to all five sizes (`none`/`sm`/`md`/`lg`/`xl`); `none` unchanged.

- **`<x-wirekit::app-shell>` sidebar breathing room.** Sidebar `<aside>` wrapper gained `lg:mt-[var(--space-wk-md,1rem)] lg:ml-[var(--padding-wk-x-lg)]` so the in-flow column position at `lg+` no longer sits flush against the header divider. Mobile / off-canvas behavior unchanged.

- **`dist/wirekit.js`** bundle grew by ~0.4 KB (the `wirekitAnimate` Alpine helper, ~1 KB minified). Full bundle is 78.0 KB. Core bundle (chart-only) unchanged.

- **`docs/cli.md`** documents all new install flags + the interactive mode + the new doctor token-alignment section + a cross-link to the dedicated `wirekit:doctor` reference page.

- **`docs/animations.md` interactive preset gallery** — fourteen toggle-button preview blocks covering all eleven `<x-wirekit::reveal>` presets (fade, slide-up, slide-down, slide-left, slide-right, scale, zoom, flip, rotate, bounce, spring) plus a duration comparison row (fast / normal / slow). Each block has a button that toggles between the `-in` and `-out` variants on successive clicks; clicks during an animation are ignored until `animationend` fires. Reduced-motion users see the final state immediately.

- **`docs/dependencies.md` bundle sizes refreshed** to reflect the v1.6.0 additions: full bundle now ~23 KB gzip (78 KB raw, +`wirekitAnimate` Alpine helper), core unchanged at ~2 KB gzip (8 KB raw), ESM ~23 KB gzip (76 KB raw). Verified via `gzip -c | wc -c`.

- **Public package archive** no longer ships `.gitattributes` or `.gitignore`. Both were dev-tooling artifacts whose only purpose was to filter the source tree at archive time; once the package is installed via `composer require pushery/wirekit`, neither file has a downstream role. Their absence from the published tarball makes developer installs marginally cleaner. v1.6.0 is the first release where this applies.

### Fixed

- **`<x-wirekit::stat animate>` reduced-motion display formatting.** Previously the reduced-motion code path snapped `value` to the raw `data-target` string (e.g. `"12500"`); the in-flight animation tick formatted via `toLocaleString()` (e.g. `"12,500"`). Result: a user with `prefers-reduced-motion: reduce` saw a different format than a user without. Both paths now share a `formatValue()` helper so display is locale-consistent regardless of motion preference. Suffix preservation (`$`, `%`, etc.) also unified across paths.

- **Sandbox `code-block` schema — `language` is now an enum** with 20 highlight.js-aligned grammar values (`bash`, `php`, `blade`, `html`, `javascript`, `typescript`, `python`, `ruby`, `go`, `rust`, `sql`, `yaml`, `markdown`, `dockerfile`, …). The `wirekit:export-api-map` output and any Sandbox-driven UI now expose the discoverable allowed-values list instead of leaving developers to guess what the syntax-highlighter accepts.

- **Sandbox `PropsValidator` — HTML-form scalar coercion** for `type: int` / `type: bool` / `type: float`. HTML form submissions are always strings (`"4"` from a `<select>`, `""` from an unchecked checkbox); previously the strict type check rejected with `expected int, got string`. The validator now conservatively coerces unambiguous string shapes into the declared scalar type before the type check, while preserving rejection for non-numeric strings against `int`. Makes every schema with a typed-int / typed-bool prop usable from a Live-Sandbox UI without developers needing to coerce client-side.

---

## [1.5.0] — 2026-04-28

Minor release with eight developer-facing improvements: a code-block screen-reader announcement fix, a `wirekit:doctor` post-build CSS sanity check, a hero-row `xl` size on `<x-wirekit::feature>`, a counter-animation `animate` prop on `<x-wirekit::stat>`, an `asideWidth` ratio refinement on `<x-wirekit::hero>`, eight new marketing-copy semantic aliases on `heroicons-marketing`, plus the syntax-highlighter contract documented in `docs/theming.md`.

No breaking changes — fully backward-compatible with v1.4.0.

### Added

- **`<x-wirekit::stat animate>`** — opt-in counter-animation prop. When set, the value text wraps in an Alpine `wirekitStatAnimate` data handler that animates 0 → target over 1.2s (ease-out cubic) once the stat scrolls 40% into view (`IntersectionObserver`). Respects `prefers-reduced-motion: reduce` — the value snaps to target with no animation if the OS-level setting is enabled. Static value remains visible inside the `<span x-text="value">` fallback so search engines, no-JS browsers, and Alpine-pre-init paint all see the real number. Default `false` preserves v1.4.x output. Numeric prefixes / suffixes (`$`, `%`, etc.) are preserved through the animation; `toLocaleString()` formats the in-flight value with locale-aware thousand-separators.

- **`<x-wirekit::hero asideWidth>`** — opt-in copy:aside ratio refinement under `layout="balanced"`. Five values: `1/3` (aside ⅓), `2/5` (aside ⅖), `1/2` (50/50, matches default), `3/5` (aside ⅗), `2/3` (aside ⅔). Under any non-balanced layout (`lead` / `centered` / `stacked`) `asideWidth` throws via `WireKit::validateProp` in debug mode and is silently ignored in production. Default `null` preserves v1.4.x balanced 50/50.

- **`<x-wirekit::feature size="xl">`** — fourth chip-size value for hero-row features. Renders a 64×64 chip with a 32×32 inner icon. Existing `sm` / `md` / `lg` values keep their semantics; `md` remains the default.

- **Eight semantic copy aliases on the `heroicons-marketing` preset** — `live` (signal), `pulse` (arrow-path-rounded-square), `a11y` (finger-print), `sparkle` (sparkles), `security` (lock-closed), `speed` (bolt), `open-source` (code-bracket), `ai` (cpu-chip). Names map to landing-page bullet copy rather than to the underlying icon name. Anti-collision verified by existing test suite — none of these shadow a base or `heroicons-app` alias.

- **Post-build CSS sanity check on `wirekit:doctor` / `wirekit:verify`** — final check after the existing source-side `@source` verification. If `public/build/manifest.json` exists, the doctor walks every CSS file in the manifest and looks for any `--color-wk-*` token reference. Fails with a "run `npm run build`" hint if no CSS bundle in the manifest references WireKit tokens — catches the silent-failure mode where a developer adds the `@source` line to `app.css` but forgets to rebuild. Skips silently in environments without a manifest (dev / pre-build / package-test scenarios).

- **`docs/theming.md` "Syntax-highlighter contract" subsection** — under Accessibility & Contrast. Tells developers wiring their own `highlight.js` / Prism / Shiki theme that token-level contrast must hit ≥4.5:1 against the active `--color-wk-bg-elevated`, in BOTH light and dark mode, across every theme preset. Documents the two foot-guns (unscoped WCAG overrides bleeding cross-mode; per-theme `bg-elevated` variations breaking single-pair audits) with a sketch of a Playwright contrast-audit recipe.

### Fixed

- **`<x-wirekit::code-block copy>` symmetric screen-reader announcements.** The polite live region (`<span role="status" aria-live="polite">`) was already in DOM but only spoke on success; the error path was silent (clipboard.writeText() rejection on non-secure-context or denied-permission cases threw an unhandled rejection and the user heard nothing). Click handler now wraps the clipboard write in `.then()/.catch()` so success AND failure each set a distinct, polite SR-only string: "Code copied to clipboard" / "Copy failed". WCAG 2.2 SC 4.1.3 (Status Messages) — both code paths now satisfy.

---

## [1.4.0] — 2026-04-28

Minor release covering accessibility, sandbox-schema, and component-render fixes that surfaced after v1.3.0 shipped, plus three additive opt-in prop additions: `<x-wirekit::action-bar mode="static">`, `<x-wirekit::toast-region eventScope>`, and the new `SandboxRenderer::BODY_WRAPPERS` map.

No breaking changes — fully backward-compatible with v1.3.0.

### Added

- **`<x-wirekit::action-bar mode="static">`** — second layout mode for the action bar. The default `mode="floating"` keeps the existing `position: fixed` + viewport-centring transforms (fully back-compat). The new `mode="static"` flows inline with surrounding content (drops the fixed positioning + the centring transforms; keeps the same chrome — border, shadow, padding, rounded corners). Useful when the bar is part of a card / panel / dashboard rather than a viewport-floating overlay.

- **`<x-wirekit::toast-region eventScope="…">`** — optional CSS selector that scopes incoming toast events by DOM containment. When set, only events whose dispatching element is inside an ancestor matching the selector are handled. Useful for "per-section toast surfaces" where multiple toast regions on the same page must not cross-talk. The existing `name` parameter (event-name routing) is unchanged and still works in parallel; `eventScope` is additive. Default `null` preserves the global-listener behaviour.

- **`Pushery\WireKit\Sandbox\SandboxRenderer::BODY_WRAPPERS` map** — auto-wraps the sandbox body slot in a sub-component for primitives whose composition requires it. The Card schema now wraps its body in `<x-wirekit::card.body>` so developer-side sandbox renders carry the full card chrome with padded body content instead of a bare rounded pill. Open extension point: future multi-slot primitives (tabs, accordion, …) can opt in by adding an entry.

### Fixed

- **Resizable handle drag no longer flickers from text-selection in adjacent panels.** The handle's `pointerdown` already called `setPointerCapture()`, but the body `user-select` was never disabled during drag — so the cursor crossing a sibling panel highlighted that text and the browser repainted the selection mid-drag (visible as a "flicker"). `onPointerDown` now captures the prior inline value of `body.style.userSelect` and sets it to `'none'`; `onPointerUp` restores it. `pointercancel` already routes through `onPointerUp` so a tab-switch mid-drag also cleans up — no leak.

- **Vertical `<x-wirekit::resizable>` no longer collapses to 0 height when the wrapper has no explicit size.** `[data-wk-resizable][data-wk-direction="vertical"]` carries `contain: size`, which requires an explicit container size — without one, the panels' percent heights resolve against a 0-height container and the whole component disappears. Added a default `min-height: 16rem` on the vertical wrapper so unstyled containers still render visibly. Authors with explicit inline `style="min-height: …"` keep their value (CSS specificity favours the inline rule).

- **WCAG 4.1.2 (Name, Role, Value) sweep across 11 interactive components.** Alpine's reactive `:aria-*` bindings only emit the attribute after the JS boots — initial server-rendered HTML lacked the required `aria-expanded` (combobox, multi-select), `aria-valuenow` (image-compare, range-slider), or `aria-checked` (rating, segmented-control). Each affected component now ships a static `aria-foo="default-value"` immediately before the `:aria-foo="reactive-binding"` so server-rendered HTML is WCAG-complete from the first paint and Alpine overrides reactively after hydration. Plus: `<x-wirekit::brand>` in logo-only mode auto-injects `aria-label="Home"` (caller's `aria-label` still wins); `<x-wirekit::toast-region>` adds `role="region"` so its `aria-label="Notifications"` is permitted; `<x-wirekit::date-picker>`, `<x-wirekit::color-picker>`, `<x-wirekit::slider>` accept a new `label` prop and fall back to a sr-only label derived from `name` when no label / `aria-label` / `aria-labelledby` is provided; `<x-wirekit::navigation-menu.item>` link-mode now falls back to the default slot when no `trigger` prop is set so the canonical `<x-wirekit::navigation-menu.item href="/x">Label</x-wirekit::navigation-menu.item>` pattern produces a non-empty link.

- **Card sandbox schema rendered as a bare rounded pill instead of a proper card.** The Card sandbox schema declared `padded` / `bordered` / `elevated` boolean props that the Card primitive never read in its `@props([...])` block — the renderer emitted them as raw HTML attributes (silently no-op), and the body slot was never wrapped in `<x-wirekit::card.body>`, so slot text pressed flush against the rounded card edges. Schema rewritten to use the real `variant` prop (`outlined` / `elevated` / `flat`); body slot now auto-wraps via the new `BODY_WRAPPERS` map.

- **`code-block` sandbox schema declared `lang`, but the actual prop is `language`.** The renderer emitted `<x-wirekit::code-block lang="php">` which the component ignored — the syntax-highlight language class was never applied. Renamed the schema key to match the component contract.

- **`text` sandbox schema declared a `muted` boolean.** The Text primitive has no `muted` prop; it uses a string `variant` enum with allowed values `default` / `muted` / `subtle` / `accent` / `success` / `warning` / `danger`. Replaced the boolean with the real `variant` enum.

- **Callout sandbox schema dropped the un-renderable `title` reference.** `title` is a named slot in the Callout primitive (`@isset($title)`), not a `@props` entry, so the renderer's string-prop-as-HTML-attribute path could never populate it. Schema now carries only the props the renderer can actually deliver. (`alert` keeps `title` because `<x-wirekit::alert>` declares `title` as a real `@props` entry — different shape.)

- **`button` and `badge` sandbox schemas: slot key `label` → `body`.** Earlier iterations declared `label` as the slot-content key, which the renderer treated as an HTML attribute and never inserted into the slot — sandbox renders produced empty buttons / badges. Renamed to the renderer's reserved `body` convention.

- **`clipboard-button` button-width regression on state change.** The first iteration of the stable-width fix used `x-show` to toggle which label rendered inside the grid cell. `x-show` sets `display: none` on the inactive label, removing it from layout entirely; the grid cell then collapsed to whichever child was visible — defeating the stable-width goal. Replaced with `:style="{ visibility: ... }"` toggling so both labels stay in layout permanently and the grid cell sizes to the wider one. The copied-state span also carries a static `style="visibility: hidden"` so its layout slot is reserved before Alpine init evaluates the bindings — no flicker on first paint, no width jump on state change.

- **`ticker` rendered `++8.4%` for already-signed-string deltas.** The delta-formatting block prepended `+` to positive deltas, then interpolated the original input verbatim — inputs that already carried an explicit sign rendered with double prefix. Strip leading `+` from string deltas before re-deriving the sign from the numeric value. Both `"8.4"` and `"+8.4"` now render identically as `+8.4%`. Negative signed strings (`"-1.2"`) keep their leading `-`. Numeric inputs (int / float) and unsigned strings remain unchanged.

- **`app-shell` defaulted to a width that didn't fill its parent.** Added `w-full` to the shell's base classes so it correctly fills its parent container regardless of layout context.

- **`code-block` defensive styling against inherited inner `<pre>` / `<code>` background.** Added `bg-transparent` and `radius-none` on the inner elements to prevent host-page CSS from bleeding through.

- **WCAG 1.4.3 contrast sweep for soft-bg foreground tokens.** Round-2 polish on calendar week-view chips, finance cells, opacity-overlaid cards, and CTA buttons — every soft-tinted background now pairs with a foreground token that meets the 4.5:1 ratio in both light and dark mode. Plus a P0 fix to `dist/wirekit.css` (`:root {}` instead of `@theme {}` so a plain `<link rel="stylesheet">` load works without the build pipeline).

- **A11y polish on shipped components**: center component fills its parent, callout becomes a `<section>` for proper landmark semantics, kanban-column scroll body gets the missing scroll-region a11y wiring.

### Changed

- **`README.md` icon section** now lists both stackable extension presets (`heroicons-app`, `heroicons-marketing`) consistently. Earlier the stackable presets only surfaced in the Available-Presets table near the bottom of the icon README block while top-of-page sections (Requirements tip, Configuration block, Switching Presets) referenced base presets only — discoverability gap closed.

---

## [1.3.0] — 2026-04-26

Minor release covering eight new blueprint primitive components, an Artisan
command suite for scaffolding / diagnostics / asset publishing / AI-tooling
integration / machine-readable manifests, the Livewire sandbox primitives library,
and a security-hardening sweep across `target="_blank"` link rendering plus the
JSON-encoder layer.

No breaking changes — fully backward-compatible with v1.2.x.

### Added

- **Blueprint primitive components (8)** — `<x-wirekit::price>` (currency formatting with size variants), `<x-wirekit::date-separator>` (timeline / chat date divider), `<x-wirekit::reaction>` (emoji reaction button with count), `<x-wirekit::ticker>` (live data ticker with delta indicator), `<x-wirekit::toolbar>` (button group bar with slots), `<x-wirekit::message>` (chat / thread message bubble with alignment), `<x-wirekit::kanban>` and `<x-wirekit::kanban-column>` (kanban board with column composition). All use design tokens exclusively, support the Intent × Surface API where applicable, and follow WAI-ARIA patterns.

- **Sandbox primitives library** (`src/Sandbox/`) — reusable security-hardened render pipeline for any developer project that needs to render WireKit components from untrusted JSON props (live-preview iframes, prop-editor UIs, etc.):
  - **`SandboxRenderer::render($component, $props, $ip): RenderResult`** — main entry point. Validates → sanitizes → renders → audit-logs. Returns `RenderResult` (success or 422-shaped rejection); never throws.
  - **`PropsValidator`** — enforces per-component prop schema, type-checks, rejects strings >10 KB and arrays nested >5 deep (DoS defence), HTML-escapes every string defence-in-depth so even a slot using `{!! !!}` cannot surface raw payload content.
  - **`ComponentAllowlist`** — strict kebab-case regex + `ComponentRegistry` cross-check + sandbox-schema presence guard. Path-traversal characters, namespace separators, whitespace, uppercase — all rejected with 422-shape, never 500.
  - **`SandboxSchemaRegistry`** — in-memory registry of per-component prop allowlists with `allowed_values` enums. Initial coverage of 11 starter components (button, badge, callout, alert, card, code, code-block, kbd, heading, text, link); the renderer is functional with whatever schemas are seeded, full coverage will follow incrementally.
  - **`SandboxAuditLog`** — file-based daily-rotating log (`storage/logs/sandbox/YYYY-MM-DD.log`). IPs sha256-truncated to 16 chars so logs are useful for rate-pattern auditing but not for tracking individuals.
  - **`RenderResult` / `ValidationResult`** — immutable result objects with public-readable `ok` / `violations` / `html` / `schema` properties so developer-side prop-editor UIs can read them via `get_object_vars()`.

- **`<x-wirekit::ticker>` dark-mode contrast fix** — switched the delta text from the bare `--color-wk-success` / `--color-wk-danger` foundation tokens to the `*-text` variants (which are calibrated for ≥4.5:1 WCAG 1.4.3 contrast against surface tokens in BOTH light and dark mode).

- **`.cursor/rules/wirekit.mdc`** — single-file (~150-line) Cursor rules ruleset covering component invocation syntax, the Intent × Surface variant system, design tokens, icon usage, layout primitives, typography primitives, modal / drawer / dropdown trigger patterns, accessibility defaults, Livewire integration patterns, browser-support baseline, and the full CLI. Cursor / Codeium / other native `.mdc` editors pick up the rules automatically for every `*.blade.php` and `*.css` file in the project.

- **`php artisan wirekit:component {name}`** — scaffolds a custom Blade component derived from a WireKit base into `resources/views/components/custom/{name}.blade.php`. `--base` flag picks the source; `--force` allows overwriting an existing custom file. Resolves both flat (`button`) and dotted (`card.header`) base names. (The v2.0.0 default of `--base={name}` was superseded in v2.1.0 by a derived-parent default — `wirekit:component my-button` now derives `--base=button` automatically; see the v2.1.0 entry.)

- **`php artisan wirekit:publish-icons {preset}`** — targeted icon publishing. Copies a single preset's SVG directory from `vendor/{package}/resources/svg/` to `public/vendor/wirekit/icons/{preset}/`. Refuses with a precise `composer require ...` fix line when the underlying icon-set package is not installed. Supports `heroicons`, `heroicons-app`, `heroicons-marketing`, `lucide`, `phosphor`, `tabler`.

- **`php artisan wirekit:doctor`** — alias for `wirekit:verify` under the more conventional Laravel-ecosystem name. Existing CI scripts and docs that reference either name keep working. (The v2.0.0 shipped two parallel registrations; v2.1.0 collapses them to a single Symfony alias — see the v2.1.0 `### Changed` entry for the internal-import consequence.)

- **`php artisan wirekit:cursor-rules`** — copies the package's `.cursor/rules/wirekit.mdc` into the developer project's `.cursor/rules/` directory. `--force` to overwrite an existing copy.

- **`php artisan wirekit:export-api-map [--pretty]`** — emits an AI-friendly hierarchical sitemap covering eight groups: components, themes, fonts, icons, layouts, blueprints, recipes, and commands. Superset of `wirekit:export-json`. Output is XSS-safe via `JSON_HEX_TAG`. Designed for MCP servers and other AI tooling that need a single entry point to enumerate every WireKit surface.

- **`php artisan wirekit:export-blocks [--pretty]`** — emits a machine-readable JSON manifest of every layout + blueprint with frontmatter metadata (`category`, `tags`, `dependencies`, `responsive`, `dark_compatible`) plus generated `preview_url` and `source_url`. Consumable by gallery UIs and AI tooling for filterable browsing.

- **Password input accessibility** — toggle button has a static `aria-label` fallback for pre-Alpine-hydration accessibility scans.

- **Toggle auto-labeling** — component auto-generates an `aria-label` from the `name` prop when neither `label` nor `aria-label` is provided.

- **Config defaults** — `config/wirekit.php` ships size defaults for `<x-wirekit::price>` and `<x-wirekit::ticker>`.

### Changed

- **`Pushery\WireKit\Sandbox\RenderResult`** now carries a public-readable `?array $schema` property in addition to `ok` / `html` / `violations`. `SandboxRenderer::success()` echoes the per-component schema back so developer-side prop-editor UIs can render the editor without a second round-trip to the schema registry.

- **`target="_blank"` auto-protection hardened** — the `rel` attribute injection in all 13 link-rendering components (Button, Dropdown Item, Command Palette Item, Menubar Item, Navigation Menu Item, Navigation Menu Link, Navbar Item, Sidebar Item, Link, Brand, Card) now uses an explicit override pattern that prevents caller-supplied `rel` values from silently defeating the `noopener noreferrer` protection. Coverage extended to four additional components that were previously missing the pattern: Link, Navbar Item, Navigation Menu Link, and Sidebar Item.

- **Chart.js dark-mode refresh** — `chart.update()` replaces `chart.update('none')` in the MutationObserver callback. Chart.js v4's `'none'` mode skips the style-resolver pass when only color properties change, leaving stale colors rendered. The observer now watches both `<html>` and `<body>` for `.dark` class changes, supporting both mounting conventions.

### Fixed

- **WCAG 1.4.3 dark-mode contrast on `<x-wirekit::ticker>` delta text** — the `--color-wk-success` / `--color-wk-danger` foundation tokens previously yielded 3.66:1 / 4.05:1 against dark surface tokens (fails the 4.5:1 AA threshold for small text). Fix uses the `*-text` token variants which are calibrated for ≥4.5:1 in both modes.

- **CTA accent variant dark-mode contrast** — the accent variant used a hardcoded `text-white` class that fails when `--color-wk-accent` inverts in dark mode. Now uses `text-[var(--color-wk-accent-fg)]` which auto-switches correctly.

- **Section accent background token** — the accent background variant referenced a non-existent `--color-wk-primary` CSS variable. Replaced with `--color-wk-accent` background and `--color-wk-accent-fg` text color, matching the established pattern used by CTA and Badge.

- **Dropdown trigger ARIA** — `aria-haspopup`, `aria-expanded`, and `aria-controls` were placed on a non-interactive `<div>` wrapper. Moved to the inner interactive element via `x-init`.

- **Progress bar accessible name** — component only wired `aria-labelledby` when the `label` prop was set. Usages without labels now receive a sensible `"Progress"` default.

- **App-shell sidebar backdrop** — the mobile sidebar dim overlay was traversable by screen readers, defeating the focus-trap intent. Added `aria-hidden="true"`.

- **`dist/wirekit.css` parses correctly when loaded via `<link>`.** Previously the entire token palette lived inside a Tailwind v4 `@theme {}` compiler block. Browsers correctly skip unknown at-rules per the CSS spec, which meant the documented "fastest path" — using the `@wirekitStyles` Blade directive that embeds the file via `<link rel="stylesheet">` — left zero `--color-wk-*` variables defined in the CSSOM. Components rendered without color tokens. The file now emits a standard `:root {}` (light) and `.dark {}` (dark) block directly, so both consumption paths resolve identically: the `@wirekitStyles` directive AND `@import` from `app.css`. The `@custom-variant dark (&:where(.dark, .dark *));` directive is preserved at the top of the file (harmless under `<link>`, useful under `@import` for Tailwind `dark:` variant support).

- **WCAG 1.4.3 contrast — light-mode `*-text` and `text-muted` tokens recalibrated.** Three "soft-bg foreground" tokens were below the AA 4.5:1 threshold against the 12% `color-mix()` soft-tone backgrounds used by badge / alert / callout / feature / message / reaction / toast-region, and `text-muted` was below threshold on `bg-muted`:
  - `--color-wk-success-text`: green-700 → green-800. Was 4.33:1 on soft-success bg, now ~6.17:1.
  - `--color-wk-danger-text`: red-500 → red-700. Was 3.89:1 on soft-danger bg, now ~5.13:1.
  - `--color-wk-warning-text`: amber-700 → amber-800. Was 4.41:1 on soft-warning bg, now ~6.04:1.
  - `--color-wk-text-muted`: neutral-500 → neutral-550. Was 4.26:1 on `bg-muted` (#f7f7f7), now ~6.13:1. `text-subtle` and `text-placeholder` are unchanged (they only appear on white where neutral-500 already meets 4.74:1).

- **Bare `text-[var(--color-wk-{success,warning,danger})]` on text content swept to `*-text` variants.** Affected component-internal usages: `<x-wirekit::text>` semantic variants (`success` / `warning`), `<x-wirekit::stat>` `up` trend, `<x-wirekit::price>` delta intent (`success` / `danger`), `<x-wirekit::feature>` soft tones (`success` / `warning`). Decorative `aria-hidden` SVG icons inside alert / callout / toast-region / code-block / rating left at the bare tone (graphic-element semantics, 3:1 threshold via WCAG 1.4.11).

- **`<x-wirekit::calendar>` event chips no longer render white text on green-500 / amber-500.** Month-view and week-view event chips (`background: var(--color-wk-success); color: var(--color-wk-bg)`) yielded 3.21:1 / 2.13:1 — fail. Component now uses `color: var(--color-wk-success-fg)` / `color: var(--color-wk-warning-fg)` (zinc-900 on the tone bg — ~6.95:1 / ~9.03:1).

- **`<x-wirekit::calendar>` week-view chip subtexts collapsed.** Opacity-blended subtexts (`<span style="opacity: 0.8">…</span>`) dropped contrast below 4.5:1 in light mode (4.23:1 against the tone bg). Replaced the two-line `Primary` + opacity-dimmed subtext pattern with a single-line `Primary · Subtext` middle-dot label that passes both modes at the full token contrast.

### Security

- **`/components.json` JSON encoder hardened with `JSON_HEX_TAG`** — brings `wirekit:export-json` in line with the existing `wirekit:export-api-map` and `wirekit:export-blocks` contracts. Without `JSON_HEX_TAG`, a component description containing `</script>` could break out of a `<script type="application/ld+json">` block where the manifest is embedded.

---

## [1.2.2] — 2026-04-20

Patch release. No developer-facing code changes — content + tooling
fixes only.

---

## [1.2.1] — 2026-04-20

Patch release. No developer-facing code changes — internal CI fix only.

---

## [1.2.0] — 2026-04-20

Substantial feature release. WireKit now covers full-page composition: a 10-component
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
  into the developer's `app.css`.
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

### Changed

- **`target="_blank"` auto-protection** — all link-rendering components
  (Button, Dropdown Item, Brand, Card, Navigation Menu Item, Link, Menubar Item,
  Command Palette Item) now automatically inject `rel="noopener noreferrer"`
  (tabnabbing prevention) and a `<span class="sr-only">(opens in new tab)</span>`
  screen-reader hint whenever `target="_blank"` is set. No developer changes
  needed. Components with both button and link modes include a guard so the
  injection only fires when `href` is present.
- **`<x-wirekit::prose>`** — gained a `variant` prop for tighter integration with
  the new typography primitives.

### Fixed

- **Grid and Feature Grid responsive classes missing from developer bundles** —
  `grid.blade.php` and `feature-grid.blade.php` built Tailwind class names via
  runtime PHP string concatenation (`"{$breakpoint}:grid-cols-{$value}"`).
  Tailwind's content scanner only sees literal strings, so these classes were
  dropped from developer CSS and previews rendered as stacked items instead of
  responsive grids. Replaced with a literal class map covering all valid
  breakpoint × column combinations (Grid: 72 entries; Feature Grid: 36 entries).
  Invalid tokens surface via `WireKit::validateProp()`.
- **Layout-doc preview rendering** — 40 `<x-wirekit::card>` occurrences in
  container, grid, stack, row, section, spacer, and divider docs wrapped raw
  content directly in the card without `<x-wirekit::card.body>`. Card provides
  border/radius/background but not padding — previews rendered as thin-bordered
  boxes with cramped text. All 40 occurrences now use `card.body`. Aspect-ratio
  preview refactored to a styled centering frame.
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
