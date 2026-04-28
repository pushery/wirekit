<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Component Prefix
    |--------------------------------------------------------------------------
    |
    | Default: 'wirekit' → <x-wirekit::button>
    | Change to 'ui' for <x-ui::button>.
    |
    */

    'prefix' => 'wirekit',

    /*
    |--------------------------------------------------------------------------
    | Component Defaults
    |--------------------------------------------------------------------------
    |
    | Default prop values per component. Overridable without publishing views.
    | These are read via config('wirekit.components.{name}.{prop}', fallback).
    |
    */

    'components' => [
        // Phase 1 — Form Components
        'button' => ['variant' => 'primary', 'size' => 'md'],
        'input' => ['size' => 'md'],
        'label' => [],
        'select' => ['size' => 'md'],
        'textarea' => ['size' => 'md', 'rows' => 3],

        // Phase 3 — Display Components
        'badge' => ['variant' => 'neutral', 'size' => 'md'],
        'card' => ['variant' => 'outlined'],
        'card.header' => [],
        'card.body' => [],
        'card.footer' => [],
        'avatar' => ['size' => 'md', 'shape' => 'circle'],
        'alert' => ['variant' => 'info'],
        'toggle' => ['size' => 'md'],
        'checkbox' => [],
        'radio' => [],
        'field' => [],

        // Phase B — Data Display Components
        'table' => ['striped' => false, 'hoverable' => false, 'compact' => false, 'responsive' => true],
        'table.head' => [],
        'table.body' => [],
        'table.foot' => [],
        'table.row' => [],
        'table.th' => [],
        'table.td' => [],
        'pagination' => ['variant' => 'full'],
        'empty-state' => [],
        'progress' => ['variant' => 'accent', 'size' => 'md', 'circle-size' => 'md'],
        'stat' => [],
        'skeleton' => [],

        // Phase 2 — Overlay Components
        'dropdown' => ['placement' => 'bottom-start', 'offset' => 8],
        'dropdown.panel' => ['width' => 'auto'],
        'dropdown.item' => [],
        'tooltip' => ['placement' => 'top', 'offset' => 6, 'delay-show' => 300, 'delay-hide' => 100],
        'modal' => ['size' => 'md', 'dismissible' => true],
        'drawer' => ['position' => 'right', 'size' => 'md', 'dismissible' => true],

        // Phase C — Navigation Components
        'tabs' => ['variant' => 'underline'],
        'breadcrumb' => ['separator' => 'chevron'],
        'accordion' => ['mode' => 'single'],
        'accordion.item' => [],
        'sidebar' => [],
        'sidebar.group' => [],
        'sidebar.item' => [],
        'stepper' => ['orientation' => 'horizontal'],

        // Phase D — Advanced Form Components
        'date-picker' => ['size' => 'md', 'format' => 'Y-m-d'],
        'file-upload' => ['size' => 'md', 'multiple' => false, 'accept' => null],
        'combobox' => ['size' => 'md', 'placeholder' => 'Select...'],
        'slider' => ['size' => 'md', 'min' => 0, 'max' => 100, 'step' => 1],
        'color-picker' => ['size' => 'md'],
        'number-input' => ['size' => 'md'],
        'password-input' => ['size' => 'md'],
        'time-picker' => ['size' => 'md'],

        // Phase E — Additional Components
        'navbar' => ['variant' => 'default'],
        'rating' => ['size' => 'md'],
        'callout' => ['variant' => 'info'],
        'data-list' => ['layout' => 'horizontal'],
        'segmented-control' => ['size' => 'md'],
        'popover' => ['placement' => 'bottom', 'offset' => 8],
        'scroll-to-top' => ['size' => 'md'],
        'alert-dialog' => ['dismissible' => false],
        'toast-region' => ['position' => 'top-right', 'duration' => 5000, 'max' => 5],
        'price' => ['size' => 'md'],
        'ticker' => ['size' => 'md'],

        // Feature card icon-chip tone + size
        'feature' => ['tone' => 'accent', 'size' => 'md'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Font Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which fonts WireKit should load. Fonts are only loaded when
    | explicitly activated here. Set to null to use the system font stack
    | (default — zero font files loaded).
    |
    | Available sans presets:
    |   'roboto', 'open-sans', 'lato', 'inter', 'montserrat',
    |   'ibm-plex-sans', 'noto-sans', 'nunito-sans', 'dm-sans',
    |   'vt323'
    |
    | Available serif presets:
    |   'playfair-display', 'lora', 'merriweather', 'ibm-plex-serif',
    |   'noto-serif'
    |
    | Available mono presets:
    |   'ibm-plex-mono', 'roboto-mono', 'source-code-pro',
    |   'jetbrains-mono', 'space-mono', 'google-sans-code'
    |
    | All fonts are bundled locally (GDPR-compliant). No external requests.
    | Run `php artisan vendor:publish --tag=wirekit-fonts` to publish fonts.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Icon Configuration
    |--------------------------------------------------------------------------
    |
    | WireKit uses 26 semantic icon aliases (e.g. 'close', 'search', 'chevron-down')
    | that are resolved via presets to actual Blade Icon identifiers.
    |
    | Available built-in presets:
    |   'heroicons'           — base, blade-ui-kit/blade-heroicons (~316 icons, Mini style)
    |   'heroicons-app'       — stackable extension with app-state aliases
    |                           (arrow-up/down, lock/unlock, bell, lightbulb, …)
    |   'heroicons-marketing' — stackable extension with marketing/landing aliases
    |                           (bolt, sparkles, rocket-launch, shield, …)
    |   'lucide'              — mallardduck/blade-lucide-icons (~1,500 icons)
    |   'phosphor'            — codeat3/blade-phosphor-icons (~9,000 icons)
    |   'tabler'              — ryangjchandler/blade-tabler-icons (~5,700 icons)
    |
    | You can also provide a fully qualified class name implementing
    | \Pushery\WireKit\Contracts\IconPreset for custom icon sets.
    |
    | Install your chosen icon set separately:
    |   composer require blade-ui-kit/blade-icons blade-ui-kit/blade-heroicons
    |
    | Stacking presets (opt-in):
    |   To compose multiple presets, use 'presets' instead of 'preset'. Later
    |   entries override earlier ones; consumer 'aliases' override all presets.
    |
    |   'presets' => ['heroicons', 'heroicons-app', 'heroicons-marketing'],
    |
    */

    'icons' => [
        // Active icon preset (default: heroicons). Use 'presets' (plural) to
        // stack multiple presets — see the comment block above.
        'preset' => 'heroicons',

        // 'presets' => ['heroicons', 'heroicons-app', 'heroicons-marketing'],

        // Override individual aliases (optional).
        // Overrides every preset for the specified aliases.
        // Useful when you prefer a specific icon from a different set.
        'aliases' => [
            // 'close' => 'lucide-x',
        ],
    ],

    'fonts' => [
        'sans' => 'inter',
        'serif' => null,
        'mono' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chart Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the chart library used by <x-wirekit-chart>.
    | Set to null to disable chart support entirely (default).
    |
    | Available adapters:
    |   'chartjs' — Chart.js (MIT, ~60KB, requires npm install chart.js)
    |
    | You can also provide a fully qualified class name implementing
    | \Pushery\WireKit\Contracts\ChartAdapter for custom chart libraries.
    |
    | Note: Chart.js must be installed separately by the developer:
    |   npm install chart.js
    |
    | And imported in your app.js:
    |   import { Chart, registerables } from 'chart.js';
    |   Chart.register(...registerables);
    |
    */

    'charts' => [
        'library' => null, // null = disabled, 'chartjs' = Chart.js
    ],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Bundle
    |--------------------------------------------------------------------------
    |
    | Choose which JavaScript bundle @wirekitScripts loads.
    |
    | 'full' — All Alpine components including overlays (~14 KB gzip)
    |          Includes Floating UI + focus-trap, bundled.
    |
    | 'core' — Only chart Alpine component (~3 KB gzip)
    |          For projects that only use form components + charts.
    |
    */

    'scripts' => [
        'bundle' => 'full', // 'full' or 'core'
    ],

];
