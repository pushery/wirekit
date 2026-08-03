# Contributing to WireKit

Thank you for your interest in contributing to WireKit!

## Setup

Development setup — prerequisites, the local quality gate, and the architecture guide — lives in the
project documentation at [docs.wirekit.app](https://docs.wirekit.app).

The commands are not repeated here on purpose. This file ships inside the published package, where
the tooling it would name is absent: the test suite, the build scripts, the linter configuration and
`package.json` are all stripped from the distributed tree. A command block here would resolve to
nothing for every reader who has one.

## Before Committing

Code style, the test suite and the Markdown lint must all pass. The exact invocations live in the
development documentation, which is also where they stay correct when they change.

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
- Dependencies are bundled in `dist/wirekit.js` — users do NOT install them separately
- Event naming: `wirekit-{component}-{action}` (kebab-case), e.g. `wirekit-modal-show`
- All Alpine components must implement `livewire:navigating` cleanup via `destroy()` lifecycle method
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
- New adapters must implement: `scripts()`, `normalizeData()`, `defaultOptions()`, `alpineComponent()`
- Built-in adapter: `ChartJsAdapter` for Chart.js

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
