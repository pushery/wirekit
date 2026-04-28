# WireKit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pushery/wirekit.svg)](https://packagist.org/packages/pushery/wirekit)
[![Total Downloads](https://img.shields.io/packagist/dt/pushery/wirekit.svg)](https://packagist.org/packages/pushery/wirekit)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A free, open-source UI component library for **Laravel Livewire** — build app dashboards and marketing pages with **Tailwind CSS v4** and **Alpine.js**, zero utility-class soup.

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

Older browsers are out of scope: WireKit ships no polyfills, no vendor-prefix fallbacks, and no shims for dropped browsers. Users on older versions will see degraded or broken rendering. If your audience still needs legacy support, WireKit is not the right fit.

## Installation

```bash
composer require pushery/wirekit
```

Add to your layout:

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

## Components

### Form Components

| Component | Tag | Docs |
|-----------|-----|------|
| Button | `<x-wirekit::button>` | [Docs](https://docs.wirekit.app/components/button) |
| Input | `<x-wirekit::input>` | [Docs](https://docs.wirekit.app/components/input) |
| Label | `<x-wirekit::label>` | [Docs](https://docs.wirekit.app/components/label) |
| Select | `<x-wirekit::select>` | [Docs](https://docs.wirekit.app/components/select) |
| Textarea | `<x-wirekit::textarea>` | [Docs](https://docs.wirekit.app/components/textarea) |
| Checkbox | `<x-wirekit::checkbox>` | [Docs](https://docs.wirekit.app/components/checkbox) |
| Radio | `<x-wirekit::radio>` | [Docs](https://docs.wirekit.app/components/radio) |
| Toggle | `<x-wirekit::toggle>` | [Docs](https://docs.wirekit.app/components/toggle) |
| Date Picker | `<x-wirekit::date-picker>` | [Docs](https://docs.wirekit.app/components/date-picker) |
| File Upload | `<x-wirekit::file-upload>` | [Docs](https://docs.wirekit.app/components/file-upload) |
| Combobox | `<x-wirekit::combobox>` | [Docs](https://docs.wirekit.app/components/combobox) |
| Slider | `<x-wirekit::slider>` | [Docs](https://docs.wirekit.app/components/slider) |
| Color Picker | `<x-wirekit::color-picker>` | [Docs](https://docs.wirekit.app/components/color-picker) |
| Field | `<x-wirekit::field>` | [Docs](https://docs.wirekit.app/components/field) |
| Number Input | `<x-wirekit::number-input>` | [Docs](https://docs.wirekit.app/components/number-input) |
| Password Input | `<x-wirekit::password-input>` | [Docs](https://docs.wirekit.app/components/password-input) |
| OTP Input | `<x-wirekit::otp-input>` | [Docs](https://docs.wirekit.app/components/otp-input) |
| Multi-Select | `<x-wirekit::multi-select>` | [Docs](https://docs.wirekit.app/components/multi-select) |
| Tags Input | `<x-wirekit::tags-input>` | [Docs](https://docs.wirekit.app/components/tags-input) |
| Rating | `<x-wirekit::rating>` | [Docs](https://docs.wirekit.app/components/rating) |
| Segmented Control | `<x-wirekit::segmented-control>` | [Docs](https://docs.wirekit.app/components/segmented-control) |
| Range Slider | `<x-wirekit::range-slider>` | [Docs](https://docs.wirekit.app/components/range-slider) |
| Time Picker | `<x-wirekit::time-picker>` | [Docs](https://docs.wirekit.app/components/time-picker) |

### Display Components

| Component | Tag | Docs |
|-----------|-----|------|
| Badge | `<x-wirekit::badge>` | [Docs](https://docs.wirekit.app/components/badge) |
| Card | `<x-wirekit::card>` | [Docs](https://docs.wirekit.app/components/card) |
| Avatar | `<x-wirekit::avatar>` | [Docs](https://docs.wirekit.app/components/avatar) |
| Alert | `<x-wirekit::alert>` | [Docs](https://docs.wirekit.app/components/alert) |
| Callout | `<x-wirekit::callout>` | [Docs](https://docs.wirekit.app/components/callout) |
| Image Compare | `<x-wirekit::image-compare>` | [Docs](https://docs.wirekit.app/components/image-compare) |
| Reaction | `<x-wirekit::reaction>` | [Docs](https://docs.wirekit.app/components/reaction) |
| Message | `<x-wirekit::message>` | [Docs](https://docs.wirekit.app/components/message) |
| Date Separator | `<x-wirekit::date-separator>` | [Docs](https://docs.wirekit.app/components/date-separator) |
| Kanban | `<x-wirekit::kanban>` | [Docs](https://docs.wirekit.app/components/kanban) |
| Kanban Column | `<x-wirekit::kanban-column>` | [Docs](https://docs.wirekit.app/components/kanban) |

### Data Display Components

| Component | Tag | Docs |
|-----------|-----|------|
| Table | `<x-wirekit::table>` | [Docs](https://docs.wirekit.app/components/table) |
| Pagination | `<x-wirekit::pagination>` | [Docs](https://docs.wirekit.app/components/pagination) |
| Empty State | `<x-wirekit::empty-state>` | [Docs](https://docs.wirekit.app/components/empty-state) |
| Progress | `<x-wirekit::progress>` | [Docs](https://docs.wirekit.app/components/progress) |
| Progress Circle | `<x-wirekit::progress.circle>` | [Docs](https://docs.wirekit.app/components/progress) |
| Stat | `<x-wirekit::stat>` | [Docs](https://docs.wirekit.app/components/stat) |
| Stats Group | `<x-wirekit::stats>` | [Docs](https://docs.wirekit.app/components/stat) |
| Skeleton | `<x-wirekit::skeleton>` | [Docs](https://docs.wirekit.app/components/skeleton) |
| Data List | `<x-wirekit::data-list>` | [Docs](https://docs.wirekit.app/components/data-list) |
| Timeline | `<x-wirekit::timeline>` | [Docs](https://docs.wirekit.app/components/timeline) |
| Tree View | `<x-wirekit::tree-view>` | [Docs](https://docs.wirekit.app/components/tree-view) |
| Ticker | `<x-wirekit::ticker>` | [Docs](https://docs.wirekit.app/components/ticker) |
| Price | `<x-wirekit::price>` | [Docs](https://docs.wirekit.app/components/price) |

### Feedback Components

| Component | Tag | Docs |
|-----------|-----|------|
| Toast | `<x-wirekit::toast-region>` | [Docs](https://docs.wirekit.app/components/toast) |

### Overlay Components

| Component | Tag | Docs |
|-----------|-----|------|
| Dropdown | `<x-wirekit::dropdown>` | [Docs](https://docs.wirekit.app/components/dropdown) |
| Tooltip | `<x-wirekit::tooltip>` | [Docs](https://docs.wirekit.app/components/tooltip) |
| Modal | `<x-wirekit::modal>` | [Docs](https://docs.wirekit.app/components/modal) |
| Drawer | `<x-wirekit::drawer>` | [Docs](https://docs.wirekit.app/components/drawer) |
| Hover Card | `<x-wirekit::hover-card>` | [Docs](https://docs.wirekit.app/components/hover-card) |
| Popover | `<x-wirekit::popover>` | [Docs](https://docs.wirekit.app/components/popover) |
| Context Menu | `<x-wirekit::context-menu>` | [Docs](https://docs.wirekit.app/components/context-menu) |
| Alert Dialog | `<x-wirekit::alert-dialog>` | [Docs](https://docs.wirekit.app/components/alert-dialog) |
| Command Palette | `<x-wirekit::command-palette>` | [Docs](https://docs.wirekit.app/components/command-palette) |

### Navigation Components

| Component | Tag | Docs |
|-----------|-----|------|
| Tabs | `<x-wirekit::tabs>` | [Docs](https://docs.wirekit.app/components/tabs) |
| Breadcrumb | `<x-wirekit::breadcrumb>` | [Docs](https://docs.wirekit.app/components/breadcrumb) |
| Accordion | `<x-wirekit::accordion>` | [Docs](https://docs.wirekit.app/components/accordion) |
| Sidebar | `<x-wirekit::sidebar>` | [Docs](https://docs.wirekit.app/components/sidebar) |
| Stepper | `<x-wirekit::stepper>` | [Docs](https://docs.wirekit.app/components/stepper) |
| Navbar | `<x-wirekit::navbar>` | [Docs](https://docs.wirekit.app/components/navbar) |
| Menubar | `<x-wirekit::menubar>` | [Docs](https://docs.wirekit.app/components/menubar) |
| Navigation Menu | `<x-wirekit::navigation-menu>` | [Docs](https://docs.wirekit.app/components/navigation-menu) |
| Toolbar | `<x-wirekit::toolbar>` | [Docs](https://docs.wirekit.app/components/toolbar) |

### Layout Components

| Component | Tag | Docs |
|-----------|-----|------|
| App Shell | `<x-wirekit::app-shell>` | [Docs](https://docs.wirekit.app/components/app-shell) |
| Header | `<x-wirekit::header>` | [Docs](https://docs.wirekit.app/components/header) |
| Main | `<x-wirekit::main>` | [Docs](https://docs.wirekit.app/components/main) |
| Brand | `<x-wirekit::brand>` | [Docs](https://docs.wirekit.app/components/brand) |
| Profile | `<x-wirekit::profile>` | [Docs](https://docs.wirekit.app/components/profile) |
| Container | `<x-wirekit::container>` | [Docs](https://docs.wirekit.app/components/container) |
| Stack | `<x-wirekit::stack>` | [Docs](https://docs.wirekit.app/components/stack) |
| Row | `<x-wirekit::row>` | [Docs](https://docs.wirekit.app/components/row) |
| Grid | `<x-wirekit::grid>` | [Docs](https://docs.wirekit.app/components/grid) |
| Section | `<x-wirekit::section>` | [Docs](https://docs.wirekit.app/components/section) |
| Spacer | `<x-wirekit::spacer>` | [Docs](https://docs.wirekit.app/components/spacer) |
| Divider | `<x-wirekit::divider>` | [Docs](https://docs.wirekit.app/components/divider) |
| Center | `<x-wirekit::center>` | [Docs](https://docs.wirekit.app/components/center) |
| Aspect Ratio | `<x-wirekit::aspect-ratio>` | [Docs](https://docs.wirekit.app/components/aspect-ratio) |
| Visually Hidden | `<x-wirekit::visually-hidden>` | [Docs](https://docs.wirekit.app/components/visually-hidden) |

### Typography Components

| Component | Tag | Docs |
|-----------|-----|------|
| Heading | `<x-wirekit::heading>` | [Docs](https://docs.wirekit.app/components/heading) |
| Text | `<x-wirekit::text>` | [Docs](https://docs.wirekit.app/components/text) |
| Link | `<x-wirekit::link>` | [Docs](https://docs.wirekit.app/components/link) |
| Code | `<x-wirekit::code>` | [Docs](https://docs.wirekit.app/components/code) |
| Code Block | `<x-wirekit::code-block>` | [Docs](https://docs.wirekit.app/components/code-block) |
| Kbd | `<x-wirekit::kbd>` | [Docs](https://docs.wirekit.app/components/kbd) |
| List | `<x-wirekit::list>` | [Docs](https://docs.wirekit.app/components/list) |
| Blockquote | `<x-wirekit::blockquote>` | [Docs](https://docs.wirekit.app/components/blockquote) |
| Mark | `<x-wirekit::mark>` | [Docs](https://docs.wirekit.app/components/mark) |
| Highlight | `<x-wirekit::highlight>` | [Docs](https://docs.wirekit.app/components/highlight) |

### Marketing Components

| Component | Tag | Docs |
|-----------|-----|------|
| Hero | `<x-wirekit::hero>` | [Docs](https://docs.wirekit.app/components/hero) |
| Feature Grid | `<x-wirekit::feature-grid>` | [Docs](https://docs.wirekit.app/components/feature-grid) |
| Feature | `<x-wirekit::feature>` | [Docs](https://docs.wirekit.app/components/feature) |
| CTA | `<x-wirekit::cta>` | [Docs](https://docs.wirekit.app/components/cta) |
| Footer | `<x-wirekit::footer>` | [Docs](https://docs.wirekit.app/components/footer) |

### Utilities

| Component | Tag | Docs |
|-----------|-----|------|
| Fonts | `<x-wirekit::fonts>` | [Docs](https://docs.wirekit.app/components/fonts) |
| Icon | `<x-wirekit::icon>` | [Docs](https://docs.wirekit.app/components/icon) |
| Chart | `<x-wirekit-chart>` | [Docs](https://docs.wirekit.app/components/chart) |
| Scroll Area | `<x-wirekit::scroll-area>` | [Docs](https://docs.wirekit.app/components/scroll-area) |
| Scrollbar | `.wk-scrollbar` (CSS class) | [Docs](https://docs.wirekit.app/components/scrollbar) |
| Scroll to Top | `<x-wirekit::scroll-to-top>` | [Docs](https://docs.wirekit.app/components/scroll-to-top) |
| Structured Data | `<x-wirekit::structured-data>` | [Docs](https://docs.wirekit.app/components/structured-data) |

### Specialized Components

| Component | Tag | Docs |
|-----------|-----|------|
| Resizable | `<x-wirekit::resizable>` | [Docs](https://docs.wirekit.app/components/resizable) |
| Carousel | `<x-wirekit::carousel>` | [Docs](https://docs.wirekit.app/components/carousel) |
| Calendar | `<x-wirekit::calendar>` | [Docs](https://docs.wirekit.app/components/calendar) |
| Tour | `<x-wirekit::tour>` | [Docs](https://docs.wirekit.app/components/tour) |
| Clipboard Button | `<x-wirekit::clipboard-button>` | [Docs](https://docs.wirekit.app/components/clipboard-button) |
| QR Code | `<x-wirekit::qr-code>` | [Docs](https://docs.wirekit.app/components/qr-code) |
| Action Bar | `<x-wirekit::action-bar>` | [Docs](https://docs.wirekit.app/components/action-bar) |
| Prose | `<x-wirekit::prose>` | [Docs](https://docs.wirekit.app/components/prose) |
| Liquid Glass | `<x-wirekit::glass>` | [Docs](https://docs.wirekit.app/components/liquid-glass) |

## Dependencies

### Bundled in `dist/wirekit.js` (no user install needed)

| Package | Version | License | Purpose | Size (gzip) |
|---------|---------|---------|---------|-------------|
| `@floating-ui/dom` | ^1.7.0 | MIT | Dropdown/Tooltip positioning | ~3.5 KB |
| `@floating-ui/core` | ^1.7.0 | MIT | Positioning core (transitive) | ~2.5 KB |
| `focus-trap` | ^8.0.0 | MIT | Modal/Drawer focus management | ~3.8 KB |
| `tabbable` | ^6.0.0 | MIT | Focusable element detection (transitive) | ~1.5 KB |

See the full [Dependencies page](https://docs.wirekit.app/dependencies) for all dependencies.

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

## Font Presets

21 Google Fonts bundled locally (GDPR-compliant). Configure in `config/wirekit.php`:

```php
'fonts' => [
    'sans'  => 'inter',
    'mono'  => 'jetbrains-mono',
],
```

Then publish and include: `php artisan vendor:publish --tag=wirekit-fonts` + `<x-wirekit::fonts />` in your layout. See the [Theming Guide](https://docs.wirekit.app/theming#font-presets) for all available fonts.

## Icon Presets

Swap icon sets with one config change. 26 semantic aliases, four base presets (`heroicons`, `lucide`, `phosphor`, `tabler`) plus two stackable extension presets (`heroicons-app`, `heroicons-marketing`) that add app-state and marketing aliases on top of a base:

```php
// Single base preset
'icons' => [
    'preset' => 'heroicons',  // or 'lucide', 'phosphor', 'tabler'
],

// Or stack a base + one or both extensions
'icons' => [
    'presets' => ['heroicons', 'heroicons-app', 'heroicons-marketing'],
],
```

Install your preferred icon set: `composer require blade-ui-kit/blade-icons blade-ui-kit/blade-heroicons`. See the [Icon docs](https://docs.wirekit.app/components/icon) for all aliases and custom presets.

## Charts

Optional chart integration with automatic WireKit theming. Dark mode updates automatically via MutationObserver — no page reload needed. Disabled by default.

```php
'charts' => ['library' => 'chartjs'],
```

```blade
<x-wirekit-chart type="bar" :labels="$months" :datasets="$datasets" />
```

Install Chart.js: `npm install chart.js`. See the [Chart docs](https://docs.wirekit.app/components/chart) for all options and custom adapters.

## Customization (4 Levels)

WireKit provides a 4-level customization system — from simple theme changes to full component overrides.

### Level 1: CSS Variables

Override theme tokens in your `app.css` (cascades on top of `wirekit.css` loaded via `@wirekitStyles`):

```css
@layer base {
    :root {
        --color-wk-accent: var(--color-blue-600);
        --color-wk-accent-hover: var(--color-blue-700);
        --color-wk-accent-fg: var(--color-white);
    }
}
```

### Level 2: PHP Defaults

Set default props globally in a service provider:

```php
WireKit::defaults([
    'button' => ['variant' => 'outline', 'size' => 'sm'],
    'input'  => ['size' => 'lg'],
]);
```

### Level 3: Deep Personalization

Replace CSS classes entirely for any component block:

```php
WireKit::personalize('button', [
    'base' => 'your-custom-classes-here',
]);
```

### Level 4: Publish Views

Full control — publish and edit the Blade templates directly:

```bash
php artisan vendor:publish --tag=wirekit-views
```

See the full [Customization Guide](https://docs.wirekit.app/customization) and [Theming Guide](https://docs.wirekit.app/theming) for details.

## Recipes

Focused composition snippets between full-page blueprints and full layouts. Each recipe is a 30–60 line preview demonstrating one pattern with copy-paste source code.

| Recipe | Demonstrates |
|--------|--------------|
| [Stat with Sparkline](https://docs.wirekit.app/recipes/stat-with-sparkline) | Stat citation + sparkline named slots |
| [Feature with Numbered Marker](https://docs.wirekit.app/recipes/feature-numbered-marker) | Feature `iconSlot` for custom step markers |
| [Hero with Code Aside](https://docs.wirekit.app/recipes/hero-with-code-aside) | Hero `aside` slot + accessible code-block |
| [Toolbar Filter Bar](https://docs.wirekit.app/recipes/toolbar-filter-bar) | Toolbar + filter selects + bulk actions |
| [Live KPI Strip](https://docs.wirekit.app/recipes/live-kpi-strip) | Ticker + `wire:poll` auto-refresh |

## Documentation

Full documentation is available at **[docs.wirekit.app](https://docs.wirekit.app)**.

| Document | Description |
|----------|-------------|
| [Getting Started](https://docs.wirekit.app/getting-started) | Installation and first steps |
| [Theming](https://docs.wirekit.app/theming) | CSS variable theming system |
| [Customization](https://docs.wirekit.app/customization) | 4-level customization guide |
| [CLI Reference](https://docs.wirekit.app/cli) | All ten `wirekit:*` Artisan commands |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guidelines and component conventions |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [LICENSE](LICENSE) | MIT License |

## License

MIT — see [LICENSE](LICENSE) for details.
