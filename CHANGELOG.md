# Changelog

All notable changes to WireKit are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.6.0] — 2026-04-29

Minor release covering a downstream field-test brief, the brief's deferred extensions, and a unified motion subsystem. Twelve chunks shipped together so the topic — fonts, doctor diagnostics, stat description options, and a real animation system — can be closed in one release. No breaking changes; every new flag, prop, opt-in surface defaults to off so v1.5.0 consumers see byte-identical output.

### Added

- **`wirekit:install --font=<sans-key>`, `--font-serif=<key>`, `--font-mono=<key>`** — three optional flags for choosing which bundled WireKit font your project uses. Resolves the key against the bundled `FontRegistry` (21 GDPR-compliant local font CSS files), validates the category, and idempotently injects an override block into `resources/css/app.css` setting BOTH `--font-{cat}` (drives Tailwind `font-{cat}` utilities) AND `--font-wk-{cat}` (drives WireKit chrome). The two stay aligned automatically — closes the foot­gun where Tailwind utilities and WireKit chrome rendered different families. All three flags combinable; re-running with the same key produces byte-identical output. Wrong-category passes throw with a list of valid keys for the right category. Local fonts only — nothing is fetched from a CDN.

- **`wirekit:install` Tailwind config writer detection** — the install command detects whether your project uses CSS-first Tailwind v4 config (`@theme` block in `app.css`) or the legacy JS-config (`tailwind.config.js theme.extend.fontFamily`) and writes the font override to the right destination. CSS-first wins on tie (Tailwind v4 deprecates JS config). Custom config shapes that the auto-edit can't safely match log an actionable manual-edit hint instead of risking AST corruption.

- **`wirekit:install` interactive mode** — running the command without flags in an interactive TTY opens a guided setup prompting for theme preset + sans/serif/mono font selection. CI / `--no-interaction` / scripted contexts skip prompts and run with v1.5.0-identical defaults.

- **`wirekit:doctor` token-alignment diagnostic** — new section comparing seven Tailwind tokens against their matching WireKit tokens: `--font-sans/serif/mono`, `--color-accent`, `--color-accent-foreground`, `--radius`, `--shadow`. Each pair emits `✓ aligned`, `⚠ mismatch` (with actionable fix hint), or `i skipped` (var() reference or unset). Surfaces token drift at install-time rather than letting it ship to production.

- **`docs/cli/wirekit-doctor.md`** — dedicated documentation page for the doctor command. Covers all twelve diagnostic checks with example output, three "Common failures and fixes" walkthroughs (font mismatch, accent-colour mismatch, var() skip), CI integration recipes (GitHub Actions + Claude Code stop-hook).

- **`<x-wirekit::stat animate>` Three Description Options** — three opt-in props govern how the description text behaves during the value count-up animation. Option A `descriptionDeferred` defers the description fade-in until the counter settles (200ms ease-out), with `aria-hidden` mirroring visibility for screen-reader contract. Option B (default, status quo) renders the description statically. Option C `descriptionAnimate` animates the description text colour from `--color-wk-text-muted` → `--color-wk-text` synchronously with the value count-up. Options A and C are mutually exclusive — passing both throws. All three honour `prefers-reduced-motion: reduce`. New `### Animation Scope` subsection in `docs/components/stat.md` documents the contract.

- **`<x-wirekit::reveal>` component** — NEW thin Blade wrapper that animates its slot content into view. One `preset` prop selects from 11 bases × in/out variants (`fade`, `slide-up`, `slide-down`, `slide-left`, `slide-right`, `scale`, `zoom`, `flip`, `rotate`, `bounce`, `spring`). Three trigger modes: `viewport` (default, IntersectionObserver), `click`, `manual` (consumer dispatches `wirekit:reveal` event). Three duration tokens: `fast` (150ms), `normal` (300ms, default), `slow` (600ms). Full `prefers-reduced-motion: reduce` honoring — element snaps to final state. New docs page `docs/components/reveal.md` with eight live preview blocks.

- **`docs/animations.md`** — NEW reference page covering the entire motion subsystem: six new design tokens, 22 keyframes (11 bases × in/out), 22 utility classes (`.wk-animate-{preset}-{in|out}` plus three duration modifiers `.wk-animate-{fast|normal|slow}`), the `wirekitAnimate` Alpine helper API, and the reduced-motion contract.

- **Six new motion design tokens** in `dist/wirekit.css`: `--motion-wk-duration-fast: 150ms`, `--motion-wk-duration-normal: 300ms`, `--motion-wk-duration-slow: 600ms`, `--motion-wk-easing-out` (decelerating cubic), `--motion-wk-easing-in` (accelerating cubic), `--motion-wk-easing-spring` (overshoot). The legacy `--transition-wk-duration` is aliased to `--motion-wk-duration-fast` for back-compat.

- **`animateIn` prop on 7 marketing components** — `<x-wirekit::card>`, `<x-wirekit::feature>`, `<x-wirekit::hero>`, `<x-wirekit::stat>`, `<x-wirekit::callout>`, `<x-wirekit::alert>`, and `<x-wirekit::empty-state>` accept an optional `animateIn` prop that wires the `wirekitAnimate` Alpine helper to the root. Pass a base name (`fade`, `slide-up`, `bounce`, …) or a full preset name (`fade-in`, `slide-up-out`). Default `null` preserves v1.5.0 render exactly. Components with built-in entrance transitions (`modal`, `drawer`, `toast`, `tooltip`, `dropdown`, `popover`) deliberately skip this prop to avoid double-motion conflicts.

- **`wirekit:export-api-map`** — new top-level `helpers` group covering Alpine helpers exposed by the WireKit JS bundle. `wirekitAnimate` lists its full 22-preset enum, three trigger modes, three duration tokens, reduced-motion contract, and Blade-wrapper hint. `wirekitStatAnimate` documents its reactive state catalog (`value`, `animating`, `progress`). MCP servers + AI tooling consume this map to suggest correct `x-data="…"` shapes without grepping the source.

- **`<x-wirekit::list>` four new ordered marker types** — `lower-roman` (i, ii, iii), `upper-roman` (I, II, III), `lower-alpha` (a, b, c), `upper-alpha` (A, B, C) join the existing `disc`, `decimal`, `none` set. All four render as `<ol>` with Tailwind v4 arbitrary-value `list-style-type` utilities. Mix freely across nested levels for legal-contract / academic / spec-style outlines.

### Changed

- **`<x-wirekit::main>` horizontal padding aligned with `<x-wirekit::header>`.** Previously `padding="lg"` produced 1.5rem all around (via `--space-wk-lg`); now uses `--padding-wk-x-{size}` for the horizontal axis (1rem at `lg`) — matching the same-name token used by Header. A sibling Header + Main pair (the canonical app-shell layout) now shares one vertical alignment line. Vertical padding stays on the generic `--space-wk-{size}` scale for breathing room. Applies to all five sizes (`none`/`sm`/`md`/`lg`/`xl`); `none` unchanged.

- **`<x-wirekit::app-shell>` sidebar breathing room.** Sidebar `<aside>` wrapper gained `lg:mt-[var(--space-wk-md,1rem)] lg:ml-[var(--padding-wk-x-lg)]` so the in-flow column position at `lg+` no longer sits flush against the header divider. Mobile / off-canvas behavior unchanged.

- **`dist/wirekit.js`** bundle grew by ~0.4 KB (the `wirekitAnimate` Alpine helper, ~1 KB minified). Full bundle is 78.0 KB. Core bundle (chart-only) unchanged.

- **`docs/cli.md`** documents all new install flags + the interactive mode + the new doctor token-alignment section + a cross-link to the dedicated `wirekit:doctor` reference page.

- **`docs/animations.md` interactive preset gallery** — fifteen click-triggered preview blocks covering all eleven `<x-wirekit::reveal>` presets (fade, slide-up, slide-down, slide-left, slide-right, scale, zoom, flip, rotate, bounce, spring) plus a duration comparison row (fast / normal / slow). Each block uses `trigger="click"` so the animation re-fires on demand instead of waiting for scroll-into-view. Reduced-motion users see the final state immediately.

- **`docs/dependencies.md` bundle sizes refreshed** to reflect the v1.6.0 additions: full bundle now ~23 KB gzip (78 KB raw, +`wirekitAnimate` Alpine helper), core unchanged at ~2 KB gzip (8 KB raw), ESM ~23 KB gzip (76 KB raw). Verified via `gzip -c | wc -c`.

- **Public package archive** no longer ships `.gitattributes` or `.gitignore`. Both were dev-tooling artifacts whose only purpose was to filter the source tree at archive time; once the package is installed via `composer require pushery/wirekit`, neither file has a downstream role. Their absence from the published tarball makes consumer installs marginally cleaner. v1.6.0 is the first release where this applies.

### Fixed

- **`<x-wirekit::stat animate>` reduced-motion display formatting.** Previously the reduced-motion code path snapped `value` to the raw `data-target` string (e.g. `"12500"`); the in-flight animation tick formatted via `toLocaleString()` (e.g. `"12,500"`). Result: a user with `prefers-reduced-motion: reduce` saw a different format than a user without. Both paths now share a `formatValue()` helper so display is locale-consistent regardless of motion preference. Suffix preservation (`$`, `%`, etc.) also unified across paths.

- **Sandbox `code-block` schema — `language` is now an enum** with 20 highlight.js-aligned grammar values (`bash`, `php`, `blade`, `html`, `javascript`, `typescript`, `python`, `ruby`, `go`, `rust`, `sql`, `yaml`, `markdown`, `dockerfile`, …). The `wirekit:export-api-map` output and any Sandbox-driven UI now expose the discoverable allowed-values list instead of leaving consumers to guess what the syntax-highlighter accepts.

- **Sandbox `PropsValidator` — HTML-form scalar coercion** for `type: int` / `type: bool` / `type: float`. HTML form submissions are always strings (`"4"` from a `<select>`, `""` from an unchecked checkbox); previously the strict type check rejected with `expected int, got string`. The validator now conservatively coerces unambiguous string shapes into the declared scalar type before the type check, while preserving rejection for non-numeric strings against `int`. Makes every schema with a typed-int / typed-bool prop usable from a Live-Sandbox UI without consumers needing to coerce client-side.

---

## [1.5.0] — 2026-04-28

Minor release covering a consumer-polish wave from real-world field-testing on a downstream landing-page build. Eight items in scope: a code-block screen-reader announcement fix, a `wirekit:doctor` post-build CSS sanity check, a hero-row `xl` size on `<x-wirekit::feature>`, a counter-animation `animate` prop on `<x-wirekit::stat>`, an `asideWidth` ratio refinement on `<x-wirekit::hero>`, eight new marketing-copy semantic aliases on `heroicons-marketing`, plus the syntax-highlighter contract documented in `docs/theming.md`.

No breaking changes — fully backward-compatible with v1.4.0.

### Added

- **`<x-wirekit::stat animate>`** — opt-in counter-animation prop. When set, the value text wraps in an Alpine `wirekitStatAnimate` data handler that animates 0 → target over 1.2s (ease-out cubic) once the stat scrolls 40% into view (`IntersectionObserver`). Respects `prefers-reduced-motion: reduce` — the value snaps to target with no animation if the OS-level setting is enabled. Static value remains visible inside the `<span x-text="value">` fallback so search engines, no-JS browsers, and Alpine-pre-init paint all see the real number. Default `false` preserves v1.4.x output. Numeric prefixes / suffixes (`$`, `%`, etc.) are preserved through the animation; `toLocaleString()` formats the in-flight value with locale-aware thousand-separators.

- **`<x-wirekit::hero asideWidth>`** — opt-in copy:aside ratio refinement under `layout="balanced"`. Five values: `1/3` (aside ⅓), `2/5` (aside ⅖), `1/2` (50/50, matches default), `3/5` (aside ⅗), `2/3` (aside ⅔). Under any non-balanced layout (`lead` / `centered` / `stacked`) `asideWidth` throws via `WireKit::validateProp` in debug mode and is silently ignored in production. Default `null` preserves v1.4.x balanced 50/50.

- **`<x-wirekit::feature size="xl">`** — fourth chip-size value for hero-row features. Renders a 64×64 chip with a 32×32 inner icon. Existing `sm` / `md` / `lg` values keep their semantics; `md` remains the default.

- **Eight semantic copy aliases on the `heroicons-marketing` preset** — `live` (signal), `pulse` (arrow-path-rounded-square), `a11y` (finger-print), `sparkle` (sparkles), `security` (lock-closed), `speed` (bolt), `open-source` (code-bracket), `ai` (cpu-chip). Names map to landing-page bullet copy rather than to the underlying icon name. Anti-collision verified by existing test suite — none of these shadow a base or `heroicons-app` alias.

- **Post-build CSS sanity check on `wirekit:doctor` / `wirekit:verify`** — final check after the existing source-side `@source` verification. If `public/build/manifest.json` exists, the doctor walks every CSS file in the manifest and looks for any `--color-wk-*` token reference. Fails with a "run `npm run build`" hint if no CSS bundle in the manifest references WireKit tokens — catches the silent-failure mode where a consumer adds the `@source` line to `app.css` but forgets to rebuild. Skips silently in environments without a manifest (dev / pre-build / package-test scenarios).

- **`docs/theming.md` "Syntax-highlighter contract" subsection** — under Accessibility & Contrast. Tells consumers wiring their own `highlight.js` / Prism / Shiki theme that token-level contrast must hit ≥4.5:1 against the active `--color-wk-bg-elevated`, in BOTH light and dark mode, across every theme preset. Documents the two foot-guns (unscoped WCAG overrides bleeding cross-mode; per-theme `bg-elevated` variations breaking single-pair audits) with a sketch of a Playwright contrast-audit recipe.

### Fixed

- **`<x-wirekit::code-block copy>` symmetric screen-reader announcements.** The polite live region (`<span role="status" aria-live="polite">`) was already in DOM but only spoke on success; the error path was silent (clipboard.writeText() rejection on non-secure-context or denied-permission cases threw an unhandled rejection and the user heard nothing). Click handler now wraps the clipboard write in `.then()/.catch()` so success AND failure each set a distinct, polite SR-only string: "Code copied to clipboard" / "Copy failed". WCAG 2.2 SC 4.1.3 (Status Messages) — both code paths now satisfy.

---

## [1.4.0] — 2026-04-28

Minor release covering accessibility, sandbox-schema, and component-render fixes that surfaced after v1.3.0 shipped, plus three additive opt-in prop additions: `<x-wirekit::action-bar mode="static">`, `<x-wirekit::toast-region eventScope>`, and the new `SandboxRenderer::BODY_WRAPPERS` map.

No breaking changes — fully backward-compatible with v1.3.0.

### Added

- **`<x-wirekit::action-bar mode="static">`** — second layout mode for the action bar. The default `mode="floating"` keeps the existing `position: fixed` + viewport-centring transforms (fully back-compat). The new `mode="static"` flows inline with surrounding content (drops the fixed positioning + the centring transforms; keeps the same chrome — border, shadow, padding, rounded corners). Useful when the bar is part of a card / panel / dashboard rather than a viewport-floating overlay.

- **`<x-wirekit::toast-region eventScope="…">`** — optional CSS selector that scopes incoming toast events by DOM containment. When set, only events whose dispatching element is inside an ancestor matching the selector are handled. Useful for "per-section toast surfaces" where multiple toast regions on the same page must not cross-talk. The existing `name` parameter (event-name routing) is unchanged and still works in parallel; `eventScope` is additive. Default `null` preserves the global-listener behaviour.

- **`Pushery\WireKit\Sandbox\SandboxRenderer::BODY_WRAPPERS` map** — auto-wraps the sandbox body slot in a sub-component for primitives whose composition requires it. The Card schema now wraps its body in `<x-wirekit::card.body>` so consumer-side sandbox renders carry the full card chrome with padded body content instead of a bare rounded pill. Open extension point: future multi-slot primitives (tabs, accordion, …) can opt in by adding an entry.

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

- **Sandbox primitives library** (`src/Sandbox/`) — reusable security-hardened render pipeline for any consumer project that needs to render WireKit components from untrusted JSON props (live-preview iframes, prop-editor UIs, etc.):
  - **`SandboxRenderer::render($component, $props, $ip): RenderResult`** — main entry point. Validates → sanitizes → renders → audit-logs. Returns `RenderResult` (success or 422-shaped rejection); never throws.
  - **`PropsValidator`** — enforces per-component prop schema, type-checks, rejects strings >10 KB and arrays nested >5 deep (DoS defence), HTML-escapes every string defence-in-depth so even a slot using `{!! !!}` cannot surface raw payload content.
  - **`ComponentAllowlist`** — strict kebab-case regex + `ComponentRegistry` cross-check + sandbox-schema presence guard. Path-traversal characters, namespace separators, whitespace, uppercase — all rejected with 422-shape, never 500.
  - **`SandboxSchemaRegistry`** — in-memory registry of per-component prop allowlists with `allowed_values` enums. Initial coverage of 11 starter components (button, badge, callout, alert, card, code, code-block, kbd, heading, text, link); the renderer is functional with whatever schemas are seeded, full coverage will follow incrementally.
  - **`SandboxAuditLog`** — file-based daily-rotating log (`storage/logs/sandbox/YYYY-MM-DD.log`). IPs sha256-truncated to 16 chars so logs are useful for rate-pattern auditing but not for tracking individuals.
  - **`RenderResult` / `ValidationResult`** — immutable result objects with public-readable `ok` / `violations` / `html` / `schema` properties so consumer-side prop-editor UIs can read them via `get_object_vars()`.

- **`<x-wirekit::ticker>` dark-mode contrast fix** — switched the delta text from the bare `--color-wk-success` / `--color-wk-danger` foundation tokens to the `*-text` variants (which are calibrated for ≥4.5:1 WCAG 1.4.3 contrast against surface tokens in BOTH light and dark mode).

- **`.cursor/rules/wirekit.mdc`** — single-file (~150-line) Cursor rules ruleset covering component invocation syntax, the Intent × Surface variant system, design tokens, icon usage, layout primitives, typography primitives, modal / drawer / dropdown trigger patterns, accessibility defaults, Livewire integration patterns, browser-support baseline, and the full CLI. Cursor / Codeium / other native `.mdc` editors pick up the rules automatically for every `*.blade.php` and `*.css` file in the project.

- **`php artisan wirekit:component {name}`** — scaffolds a custom Blade component derived from a WireKit base into `resources/views/components/custom/{name}.blade.php`. `--base` flag picks the source (defaults to `{name}`); `--force` allows overwriting an existing custom file. Resolves both flat (`button`) and dotted (`card.header`) base names.

- **`php artisan wirekit:publish-icons {preset}`** — targeted icon publishing. Copies a single preset's SVG directory from `vendor/{package}/resources/svg/` to `public/vendor/wirekit/icons/{preset}/`. Refuses with a precise `composer require ...` fix line when the underlying icon-set package is not installed. Supports `heroicons`, `heroicons-app`, `heroicons-marketing`, `lucide`, `phosphor`, `tabler`.

- **`php artisan wirekit:doctor`** — alias for `wirekit:verify` under the more conventional Laravel-ecosystem name. Both registrations stay in parallel for backward compatibility — existing CI scripts and docs that reference `wirekit:verify` keep working.

- **`php artisan wirekit:cursor-rules`** — copies the package's `.cursor/rules/wirekit.mdc` into the consumer project's `.cursor/rules/` directory. `--force` to overwrite an existing copy.

- **`php artisan wirekit:export-api-map [--pretty]`** — emits an AI-friendly hierarchical sitemap covering eight groups: components, themes, fonts, icons, layouts, blueprints, recipes, and commands. Superset of `wirekit:export-json`. Output is XSS-safe via `JSON_HEX_TAG`. Designed for MCP servers and other AI tooling that need a single entry point to enumerate every WireKit surface.

- **`php artisan wirekit:export-blocks [--pretty]`** — emits a machine-readable JSON manifest of every layout + blueprint with frontmatter metadata (`category`, `tags`, `dependencies`, `responsive`, `dark_compatible`) plus generated `preview_url` and `source_url`. Consumable by gallery UIs and AI tooling for filterable browsing.

- **`php artisan wirekit:generate-changelogs [--dry-run]`** — regenerates per-component `## Changelog` sections from filtered Git history, with full sanitization of internal-only references before writing public output.

- **Password input accessibility** — toggle button has a static `aria-label` fallback for pre-Alpine-hydration accessibility scans.

- **Toggle auto-labeling** — component auto-generates an `aria-label` from the `name` prop when neither `label` nor `aria-label` is provided.

- **Config defaults** — `config/wirekit.php` ships size defaults for `<x-wirekit::price>` and `<x-wirekit::ticker>`.

### Changed

- **`Pushery\WireKit\Sandbox\RenderResult`** now carries a public-readable `?array $schema` property in addition to `ok` / `html` / `violations`. `SandboxRenderer::success()` echoes the per-component schema back so consumer-side prop-editor UIs can render the editor without a second round-trip to the schema registry.

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

Patch release. No consumer-facing code changes — content + tooling
fixes only.

---

## [1.2.1] — 2026-04-20

Patch release. No consumer-facing code changes — internal CI fix only.

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
