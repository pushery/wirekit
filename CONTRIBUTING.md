# Contributing to WireKit

Thank you for your interest in contributing to WireKit!

## Setup

For the full developer setup guide (prerequisites, commands, architecture), see the project documentation at [docs.wirekit.app](https://docs.wirekit.app).

Quick start:

```bash
git clone https://github.com/pushery/wirekit.git && cd wirekit
composer install
npm install
```

## Pre-Commit Checklist

All of these must pass before committing:

```bash
vendor/bin/pint --test                     # Code style — 0 violations
vendor/bin/pest                            # Tests — all green
npx markdownlint-cli2 "docs/**/*.md"      # Markdown lint — 0 errors
```

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
- Two bundles available: `wirekit.js` (full, with overlay deps) and `wirekit.core.js` (chart only)

## Icon System

- 26 semantic aliases (e.g. `close`, `search`, `chevron-down`) resolved via presets
- 4 built-in presets: Heroicons, Lucide, Phosphor, Tabler
- Custom presets implement `Pushery\WireKit\Contracts\IconPreset`
- New aliases must be added to ALL 4 built-in presets
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
