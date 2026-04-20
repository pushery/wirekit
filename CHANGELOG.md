# Changelog

All notable changes to WireKit are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

*Changes since the last release that are not yet tagged.*

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
