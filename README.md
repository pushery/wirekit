<p align="center">
  <img src="resources/branding/wirekit-logo-light.png" alt="WireKit" width="320">
</p>

# WireKit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pushery/wirekit.svg)](https://packagist.org/packages/pushery/wirekit)
[![Total Downloads](https://img.shields.io/packagist/dt/pushery/wirekit.svg)](https://packagist.org/packages/pushery/wirekit)
[![Tests](https://github.com/pushery/wirekit/actions/workflows/tests.yml/badge.svg)](https://github.com/pushery/wirekit/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/pushery/wirekit?style=flat&logo=github)](https://github.com/pushery/wirekit/stargazers)
[![Components](https://img.shields.io/badge/components-110-5046e5)](https://docs.wirekit.app/components)

[![PHP ≥ 8.4](https://img.shields.io/packagist/dependency-v/pushery/wirekit/php?logo=php&logoColor=white&color=777BB4&label=PHP)](https://www.php.net)
[![Laravel 12+](https://img.shields.io/badge/Laravel-12+-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire 4+](https://img.shields.io/badge/Livewire-4+-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS v4](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-%E2%9C%93-8BC0D0?logo=alpinedotjs&logoColor=white)](https://alpinejs.dev)

A free, open-source UI component library for **Laravel Livewire** — build app dashboards and marketing pages with **Tailwind CSS v4** and **Alpine.js**, zero utility-class soup.

**110 components** across forms, navigation, overlays, layout, marketing, data display, and more — fully themeable, accessible by default, and dark-mode aware.

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

Full setup walkthrough: **[Getting Started](https://docs.wirekit.app/getting-started)** · **[Integration Guide](https://docs.wirekit.app/integration)**

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

110 components organised into 11 categories. Browse, search, and try every component live on **[docs.wirekit.app/components](https://docs.wirekit.app/components)**.

| Category | Examples |
|----------|----------|
| **Forms** | button, input, select, textarea, combobox, multi-select, date-picker, slider, color-picker, otp-input, … |
| **Display** | badge, card, avatar, alert, callout, image-compare, kanban, reveal, … |
| **Data Display** | table, pagination, stat, progress, skeleton, timeline, tree-view, ticker, price, … |
| **Overlays** | dropdown, tooltip, modal, drawer, popover, hover-card, command-palette, alert-dialog, … |
| **Navigation** | tabs, breadcrumb, accordion, sidebar, navbar, menubar, navigation-menu, stepper, … |
| **Layout** | app-shell, header, main, container, stack, grid, section, divider, … |
| **Typography** | heading, text, link, code, code-block, kbd, list, blockquote, mark, … |
| **Marketing** | hero, feature-grid, feature, cta, footer |
| **Utilities** | fonts, icon, chart, scroll-area, scroll-to-top, structured-data |
| **Specialized** | resizable, carousel, calendar, tour, qr-code, action-bar, prose, liquid-glass |
| **Feedback** | toast |

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

- **Fonts** — 21 Google Fonts bundled locally (GDPR-compliant). Configure via `config/wirekit.php`.
- **Icons** — Stackable presets for `heroicons`, `lucide`, `phosphor`, `tabler` plus app/marketing extensions. 26 semantic aliases.
- **Charts** — Optional Chart.js integration with automatic WireKit theming and dark-mode reactivity.

→ **[Theming Guide](https://docs.wirekit.app/theming)** for fonts and presets · **[Icon docs](https://docs.wirekit.app/components/icon)** · **[Chart docs](https://docs.wirekit.app/components/chart)**

## Documentation

| Resource | Where |
|----------|-------|
| Full documentation | [docs.wirekit.app](https://docs.wirekit.app) |
| Component reference | [docs.wirekit.app/components](https://docs.wirekit.app/components) |
| Recipes & blueprints | [docs.wirekit.app/recipes](https://docs.wirekit.app/recipes) |
| CLI reference | [docs.wirekit.app/cli](https://docs.wirekit.app/cli) |
| Contribution guide | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Release history | [CHANGELOG.md](CHANGELOG.md) |

## License

MIT — see [LICENSE](LICENSE) for details.
