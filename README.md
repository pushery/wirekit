<p align="center">
  <img src="resources/branding/wirekit-logo-light.png" alt="WireKit" width="320">
</p>

# WireKit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pushery/wirekit.svg)](https://packagist.org/packages/pushery/wirekit)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Components](https://img.shields.io/badge/components-browse-5046e5)](https://docs.wirekit.app)

[![PHP ≥ 8.4](https://img.shields.io/packagist/dependency-v/pushery/wirekit/php?logo=php&logoColor=white&color=777BB4&label=PHP)](https://www.php.net)
[![Laravel 12+](https://img.shields.io/badge/Laravel-12+-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire 4+](https://img.shields.io/badge/Livewire-4+-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS v4](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-%E2%9C%93-8BC0D0?logo=alpinedotjs&logoColor=white)](https://alpinejs.dev)

A free, open-source UI component library for **Laravel Livewire** — build app dashboards and marketing pages with **Tailwind CSS v4** and **Alpine.js**, zero utility-class soup.

A comprehensive component library covering forms, navigation, overlays, layout, marketing, data display, and more — fully themeable, accessible by default, and dark-mode aware.

→ **Full documentation: [docs.wirekit.app](https://docs.wirekit.app)**

## Requirements

- PHP 8.4+
- Laravel 12+
- Livewire 4+
- Tailwind CSS v4
- Alpine.js

## Browser Support

WireKit's supported-browser baseline is **pinned to [Tailwind CSS v4's official requirements](https://tailwindcss.com/docs/compatibility)** — whenever Tailwind raises its baseline, WireKit follows in the same release.

| Browser | Minimum version | Released |
|---------|-----------------|----------|
| **Chrome** | 111 | March 2023 |
| **Edge** | 111 | March 2023 (Chromium-based) |
| **Safari** | 16.4 | March 2023 |
| **Firefox** | 128 | July 2024 |

Older browsers are out of scope: WireKit ships no polyfills, no vendor-prefix fallbacks, and no shims for dropped browsers.

## Installation

```bash
composer require pushery/wirekit
```

Add the directives to your layout:

```blade
<head>
    @wirekitStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}
    @wirekitScripts
</body>
```

Add WireKit's Blade source path to your `resources/css/app.css`:

```css
@import 'tailwindcss';
@source '../../vendor/pushery/wirekit/resources/views/**/*.blade.php';
```

Full setup walkthrough: **[Getting Started](https://docs.wirekit.app/getting-started)** · **[Integration Guide](https://docs.wirekit.app/getting-started/integration)**

## Quick Start

```blade
<x-wirekit::button>Save</x-wirekit::button>
<x-wirekit::button variant="danger">Delete</x-wirekit::button>

<x-wirekit::input label="Email" type="email" name="email" wire:model="email" />

<x-wirekit::select
    label="Role"
    name="role"
    :options="['admin' => 'Admin', 'user' => 'User']"
/>

<x-wirekit::textarea label="Bio" name="bio" wire:model="bio" />
```

## What's Included

A wide catalog of components organized by category. Browse, search, and try every component live on **[docs.wirekit.app](https://docs.wirekit.app)**.

| Category | Examples |
|----------|----------|
| **Forms** | button, input, select, textarea, editor, combobox, multi-select, date-picker, slider, color-picker, otp-input, filter-builder, … |
| **Display** | badge, card, avatar, alert, callout, countdown, image, image-gallery, image-compare, kanban, stage-card, activity-row, reveal, … |
| **Data Display** | table, data-table, status-matrix, notification-center, pagination, stat, stats, progress, radial-progress, usage-meter, skeleton, spinner, timeline, tree-view, ticker, price, … |
| **Overlays** | dropdown, tooltip, modal, drawer, popover, hover-card, lightbox, command-palette, alert-dialog, … |
| **Navigation** | tabs, breadcrumb, accordion, collapsible, sidebar, navbar, brand-bar, menubar, navigation-menu, stepper, … |
| **Layout** | app-shell, header, main, footer, container, stack, grid, section, divider, sticky-panel, skip-link, spine-aware, … |
| **Typography** | heading, text, link, code, code-block, kbd, list, blockquote, mark, … |
| **Marketing** | hero, feature-grid, feature, cta |
| **Utilities** | fonts, icon, chart, chart-mixed, map, sparkline, scroll-area, scroll-to-top, structured-data |
| **Specialized** | resizable, carousel, calendar, event-calendar, tour, qr-code, action-bar, prose, glass |
| **Reading** | reading-progress, reading-spine, reading-toc, reading-minimap, reading-bookmark, reading-meta, reading-shell |
| **Animation** | reveal, replay-button |
| **Feedback** | toast-region |

## Theming & Customization

WireKit ships with a **4-level customization system** — from CSS-variable theme tokens to fully published Blade views. Every component reads from `--color-wk-*` design tokens with built-in dark-mode support.

```css
@layer base {
    :root {
        --color-wk-accent: var(--color-blue-600);
    }
}
```

→ **[Theming Guide](https://docs.wirekit.app/theming)** · **[Customization Guide](https://docs.wirekit.app/customization)**

## Optional Integrations

- **Fonts** — Curated Google Fonts bundled locally for GDPR compliance. Configure via `config/wirekit.php`.
- **Icons** — Stackable presets for `heroicons`, `lucide`, `phosphor`, `tabler` plus app/marketing extensions, with semantic aliases for common UI intents.
- **Charts** — Optional chart system with a Chart.js (MIT) adapter and an ApexCharts adapter. Switch the app default with one line: `'charts' => ['library' => 'apexcharts']` in `config/wirekit.php`, or override per-instance via `<x-wirekit-chart library="apexcharts" …>` for mixed-library pages. ApexCharts is **non-MIT** (free Community License under $2M USD revenue, Commercial License above) — WireKit ships only the adapter glue. See [Chart docs](https://docs.wirekit.app/components/chart) for the full terms.

→ **[Theming Guide](https://docs.wirekit.app/theming)** for fonts and presets · **[Icon docs](https://docs.wirekit.app/components/icon)** · **[Chart docs](https://docs.wirekit.app/components/chart)**

## Using WireKit with AI assistants

WireKit ships first-class context for AI coding assistants so they author correct
markup instead of guessing at props. From your project root:

- `php artisan wirekit:list` — every component, grouped by category
- `php artisan wirekit:show <component>` — props, slots, sub-components, docs URL
- `php artisan wirekit:icons` — every icon alias, grouped by preset
- `php artisan wirekit:export-json --pretty` — the full machine-readable manifest

`wirekit:install` also writes a `.wirekit-schema.json` to your project root for
zero-configuration autocomplete. A tool-neutral [`AGENTS.md`](AGENTS.md) and a
`.cursor/rules/wirekit.mdc` rule file (publish with `php artisan wirekit:cursor-rules`)
ship with the package, and an MCP server exposes the same catalog as live editor
tools.

→ **[AI tooling guide](https://docs.wirekit.app/ai-tooling)**

## Documentation

| Resource | Where |
|----------|-------|
| Full documentation | [docs.wirekit.app](https://docs.wirekit.app) |
| Getting started | [docs.wirekit.app/getting-started](https://docs.wirekit.app/getting-started) |
| Theming | [docs.wirekit.app/theming](https://docs.wirekit.app/theming) |
| CLI reference | [docs.wirekit.app/cli](https://docs.wirekit.app/cli) |
| Contribution guide | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Changelog | [docs.wirekit.app/changelog](https://docs.wirekit.app/changelog) · [CHANGELOG.md](CHANGELOG.md) |

## License

MIT — see [LICENSE](LICENSE) for details.
