# Contributing to WireKit

Thank you for your interest in contributing to WireKit!

## Setup

Development happens in the project repository, not in the published package. This file ships inside
the package, where the tooling a setup guide would name is absent: the test suite, the build
scripts, the linter configuration and `package.json` are all stripped from the distributed tree. A
command block here would resolve to nothing for every reader who has one, and the repository carries
its own setup guide next to the tooling it describes — which is where it stays correct.

Issues and pull requests go to [github.com/pushery/wirekit](https://github.com/pushery/wirekit).

## Before Committing

Code style, the test suite and the Markdown lint must all pass. Their invocations live in the
repository's own development guide, beside the configuration they run against.

## Component Conventions

### General Rules

- Use `WireKit::resolveClasses()` for ALL CSS classes — no hardcoded class strings
- Use `--color-wk-*` CSS variables for ALL colors — NEVER hardcode `zinc-*`, `gray-*`, or any Tailwind color
- CSS variables auto-switch in dark mode — do NOT add `dark:` prefix to `var(--color-wk-*)` classes
- ALL visual properties via design tokens: radius, shadow, typography, motion, sizing, padding
- Support `scope` prop for scoped personalization
- Props default via `config('wirekit.components.{name}.{prop}', fallback)`
- Use `$attributes->class([...])` for user class merging
- Every component needs tests AND a docs page in `docs/components/`

### Anonymous vs. Class-based Components

- **Anonymous** (default): Simple Blade templates in `resources/views/components/`. Use for components that don't need dependency injection. Example: Button, Input, Label, Dropdown, Modal.
- **Class-based**: PHP class in `src/Components/` + Blade view. Use only when DI or complex logic is needed. Example: Chart (needs `ChartManager` injection).
- Naming: `<x-wirekit::*>` for anonymous, `<x-wirekit-*>` for class-based

### Sub-Components

- Use dot-syntax for sub-components: `dropdown.trigger`, `modal.close`, `drawer.header`
- Place in subdirectories: `resources/views/components/dropdown/trigger.blade.php`
- Shared partials go in `resources/views/components/partials/` (e.g., `overlay-close.blade.php`)

### CSS Token Rules

- No hardcoded Tailwind classes — always use `var(--*-wk-*)` tokens
- Every component must call `WireKit::resolveClasses()` for every CSS block
- Every component gets a `scope` prop for scoped personalization

### ARIA / Accessibility

- **Form components**: `aria-invalid`, `aria-describedby` for errors/hints, `<label>` with `for`
- **Overlay components**: `aria-haspopup`, `aria-expanded`, `aria-controls`, `aria-labelledby`, focus trap, scroll lock, ESC to close
- **Utility components**: keyboard-accessible, visible focus rings via `focus-visible:ring-*`

## JavaScript Conventions

- Alpine components are registered as `wirekitComponentName` (camelCase with `wirekit` prefix)
- The Alpine-side dependencies — Floating UI, focus-trap, `@alpinejs/collapse` — are bundled in
  `dist/wirekit.js`, so a developer does not install them separately. The rendering engines behind
  chart, editor and map are NOT bundled: they are peer installs the developer adds, and the
  components say so at runtime when one is missing. Adding a bundled dependency means widening the
  allowlist in `scripts/test-bundle-dependencies.mjs`, with the reason written beside it.
- Event naming: `wirekit-{component}-{action}` (kebab-case), e.g. `wirekit-modal-show`
- Every Alpine component that holds a listener, observer, timer or animation frame releases it in
  `destroy()` — Alpine calls that hook when the element goes away. A component that keeps state
  which must not survive an SPA navigation additionally listens for `livewire:navigating`, the way
  the overlay helper closes an open overlay before the page changes underneath it
- Several bundles ship, each for a different loading strategy — `dist/README.md` is the decision tree and it sits beside them

## Icon System

- A shared semantic alias vocabulary (e.g. `close`, `search`, `chevron-down`) resolved via presets
- Four base presets — Heroicons, Lucide, Phosphor, Tabler — plus two stackable Heroicons extensions (`heroicons-app`, `heroicons-marketing`)
- Custom presets implement `Pushery\WireKit\Contracts\IconPreset`
- A new alias must be added to all four base presets, so it resolves whichever one is configured
- Config overrides allow per-alias customization without a full preset

## Chart System

- Adapter pattern: implement `Pushery\WireKit\Contracts\ChartAdapter`
- Charts are disabled by default (`charts.library = null`)
- The interface is not optional in parts — an adapter implements all seven methods or PHP refuses to
  instantiate it: `name()`, `scripts()`, `normalizeData()`, `defaultOptions()`, `alpineComponent()`,
  `rendersTo()`, `supportedTypes()`
- Two adapters ship: `ChartJsAdapter` for Chart.js and `ApexChartsAdapter` for ApexCharts

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```text
feat: add modal component
fix: correct dark mode border on select
docs: update theming guide
test: add error state tests for textarea
```

## License

By contributing, you agree your contributions will be licensed under the MIT License.
