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
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 currency code the <x-wirekit::price> / <x-wirekit::pricing-tier>
    | components use when no per-call `currency` is passed. Uppercase (EUR, USD,
    | GBP). The components read this key; it is declared here so a global default
    | is settable AND visible.
    |
    */

    'currency' => env('WIREKIT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Runtime Validation Strictness
    |--------------------------------------------------------------------------
    |
    | Decides how WireKit reacts when a component is rendered with a value
    | outside the allowed list — invalid prop value, unknown icon alias,
    | etc. See `Pushery\WireKit\Support\StrictnessGate` for the gate's
    | central decision.
    |
    | null  — Default. Strict in debug (APP_DEBUG=true), lenient in prod.
    |         Most apps want this — invalid props blow up in development
    |         where the developer can fix them, but a typo doesn't crash
    |         a customer-facing page in production.
    |
    | true  — Force strict everywhere (CI / staging hardening).
    |         InvalidArgumentException is thrown with a Did-you-mean hint
    |         on every miss, in dev AND in prod.
    |
    | false — Force lenient everywhere (snapshot CI, deliberate fallback).
    |         The first allowed value is returned silently after a
    |         logger->warning() call. No exception, even in debug.
    |
    */

    'validation' => [
        'strict' => env('WIREKIT_STRICT_VALIDATION'),

        // Whether an invalid value (a bad prop value, an unknown icon alias)
        // should THROW rather than degrade to a fallback. Null (default) uses the
        // context: console / artisan / test fail fast so a typo breaks the build
        // loudly, while an HTTP request degrades so one bad value cannot 500 a
        // whole view (an unknown icon renders an inert placeholder). Set true to
        // always throw, false to always degrade — an explicit value wins in both
        // directions.
        'throw_on_invalid' => env('WIREKIT_THROW_ON_INVALID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doctor
    |--------------------------------------------------------------------------
    |
    | Configuration for the `wirekit:doctor` command. The doctor is a read-only
    | diagnostic that surfaces install / asset / env issues. Toggles below let
    | developers disable individual opt-in helpers in environments where they
    | don't apply (e.g. apps with custom log channels).
    |
    | scan_logs: when true (default), the doctor scans storage/logs/laravel*.log
    | for `WireKit [...]` ERROR / WARNING lines emitted by StrictnessGate's
    | HTTP-dev fallback path. The scan SAFE-DEGRADES at every failure mode
    | (missing log file, non-file log channel, unreadable file) — set to false
    | only when you want to suppress the helper entirely.
    |
    */

    'doctor' => [
        'scan_logs' => env('WIREKIT_DOCTOR_SCAN_LOGS', true),

        // How far back the log scan looks, in hours. Without a bound the check reads the
        // whole history, so a typo fixed weeks ago keeps its line in laravel.log forever
        // and the warning stays yellow for the life of the file — a developer who did
        // exactly what they were told sees no change, and learns to stop reading it.
        //
        // 0 restores the old read-everything behavior.
        'scan_logs_window_hours' => env('WIREKIT_DOCTOR_SCAN_LOGS_WINDOW_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Defaults
    |--------------------------------------------------------------------------
    |
    | Default prop values per component. Overridable without publishing views.
    | These are read via config('wirekit.components.{name}.{prop}', fallback).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Where the theme-controller stores the reader's choice. The key is shared
    | by the control and by the @wirekitThemeScript head script — they must agree
    | or the page paints one theme and then switches to the other.
    |
    | `storage` picks the backing store:
    |   'local'  — localStorage (default, client-only). The head script must read
    |              it and apply `.dark` before paint; the server never sees it.
    |   'cookie' — document.cookie, which the SERVER can read on the next request.
    |              With this driver your Laravel layout can resolve the theme from
    |              the request cookie and render <html class="dark"> itself, so the
    |              first paint is already correct and the head script is only a
    |              safety net for the 'system' case. Required for any server-
    |              rendered dark mode. IMPORTANT: the control writes the cookie from
    |              JavaScript (unencrypted), so if your app runs the EncryptCookies
    |              middleware you MUST add this key to its `$except` list, or Laravel
    |              drops it as tampered on the server read. `cookie_attributes`
    |              tunes the written cookie (a UI preference: Lax + one-year Max-Age
    |              is the sensible default; Secure is added automatically on HTTPS).
    |
    */
    'theme' => [
        'storage' => env('WIREKIT_THEME_STORAGE', 'local'),
        'storage_key' => env('WIREKIT_THEME_STORAGE_KEY', 'wirekit-theme'),
        'cookie_attributes' => [
            'same_site' => env('WIREKIT_THEME_COOKIE_SAME_SITE', 'Lax'),
            'max_age' => (int) env('WIREKIT_THEME_COOKIE_MAX_AGE', 31536000),
            'path' => env('WIREKIT_THEME_COOKIE_PATH', '/'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    |
    | Global a11y defaults. `announce_error` controls whether form controls
    | wrap their error message in an aria-live="polite" region so screen
    | readers announce it as it appears. Turn it OFF app-wide when your app
    | runs its OWN error live-region and would otherwise double-announce; a
    | per-control `:announce-error` still overrides this default.
    |
    */
    'a11y' => [
        'announce_error' => env('WIREKIT_ANNOUNCE_ERROR', true),

        // Give each form control a page-unique DOM id even when several share a
        // `name` (a create + an edit form, a filter bar + a modal, a repeater row).
        // The first occurrence keeps the clean derived id; later collisions get a
        // -2/-3 suffix, so `label[for]` and `aria-describedby` always resolve to
        // the right control. Set false to restore the pre-2.20 verbatim behavior.
        'dedupe_ids' => env('WIREKIT_DEDUPE_IDS', true),

        // Motion is decided by the operating system unless your application
        // states otherwise. Set `data-reduce-motion` on <html> to override it:
        //
        //   reduce          reduce motion whatever the OS says
        //   no-preference   do NOT reduce, even though the OS asks — for someone
        //                   who set the OS flag for an unrelated reason
        //   attribute absent  follow the OS (the default)
        //
        // The middle state is why an attribute exists at all: a media query can
        // express two states and an application needs three. Both halves of the
        // library read the same attribute — the stylesheet for transitions, the
        // Alpine components for the decisions CSS cannot make (stopping an
        // auto-advance, revealing a streamed buffer at once) — so they cannot
        // disagree on the same page.
        //
        // THIS IS A RECORD, NOT A SWITCH. Changing it changes nothing.
        //
        // It used to say the opposite — "every rule and every component reads this
        // name" — and that was never true. Nothing reads this key: not a component,
        // not a class, not the JavaScript. Both halves carry the name literally, the
        // stylesheet in 26 selectors and `resources/js/utils/motion.js` in its
        // MOTION_ATTRIBUTE constant. It also had an `env()` seam, so setting
        // WIREKIT_MOTION_ATTRIBUTE in .env produced no effect and no error — an
        // interface that looks like an interface and is not, which is worse than
        // having none.
        //
        // It stays because the value is worth knowing: writing your own
        // reduced-motion rules means matching this attribute, and reading it here
        // beats grepping a minified bundle. It is now GUARDED — the build fails if
        // this string stops matching what the shipped CSS and every JS bundle
        // actually carry — so it is a checked fact rather than a decorative one.
        //
        // Making it a real switch was considered and rejected on the merits, not on
        // effort: `[data-reduce-motion]` is a structural selector, so no CSS variable
        // can parameterize it. A configurable name would mean giving up the prebuilt
        // stylesheet and having every developer compile their own — a change to the
        // architecture in exchange for renaming an attribute nobody has needed to
        // rename.
        'motion_attribute' => 'data-reduce-motion',

        // The attribute an increased-contrast preference is expressed with, and like the
        // one above this is a RECORD of what the stylesheet carries rather than a switch:
        // `dist/wirekit.css` names it literally in its selectors, so changing this string
        // changes nothing. It is here to be read.
        //
        // Three states, because two of them cannot be written as a media query:
        //   data-contrast="more"           an explicit request — wins over the OS setting
        //   data-contrast="no-preference"  an explicit refusal — also wins over the OS
        //   (absent)                       follow `prefers-contrast`
        //
        // What it affects is the muted end of the palette: helper text, placeholders and
        // borders — the values a reader with low vision loses first. Set it on <html> from
        // your own preference UI, the same way you set `.dark`.
        'contrast_attribute' => 'data-contrast',

        /*
         * The third preference, and the one that is a custom property rather than an
         * attribute — because it carries a NUMBER, not a state. Motion and contrast each
         * have three named positions; type size is a factor, and an application setting
         * `1.25` should not have to pick from a list somebody else guessed at.
         *
         * Set it on the root element and the whole ramp moves together:
         *
         *     <html style="--font-scale-wk: 1.25">
         *
         * Like its two siblings, this key is a RECORD of the name the shipped stylesheet
         * carries, not a switch that renames it — the same correction the motion key
         * received in this release, applied from the start rather than after the fact.
         * `FontScaleGuardTest` holds the two together.
         */
        'font_scale_property' => '--font-scale-wk',
    ],

    'components' => [
        // Form components
        'button' => ['intent' => 'primary', 'surface' => 'filled', 'size' => 'md'],
        'swap' => ['effect' => 'fade'],
        'theme-controller' => ['variant' => 'button', 'size' => 'md', 'surface' => 'filled'],
        'fab' => ['position' => 'end'],
        'fab.button' => [],
        'button.group' => [],
        'input' => ['size' => 'md'],
        'label' => [],
        'select' => ['size' => 'md'],
        'textarea' => ['size' => 'md', 'rows' => 3],
        // The editor engine is a peer dependency (Tiptap recommended: install
        // @tiptap/core + @tiptap/starter-kit and expose window.wirekitEditor — the
        // legacy window.tiptapEditor name still works as a deprecated alias).
        // `extensions` is an OPTIONAL list of name hints forwarded to YOUR factory;
        // the factory owns the real extension set (see docs/components/editor.md). It
        // defaults to an empty array so a factory that naively spreads it can't throw on a
        // bare string — set your own hints here only if your factory reads them.
        'editor' => ['extensions' => [], 'format' => 'html', 'toolbar' => 'basic', 'size' => 'md'],
        'editor.toolbar' => [],

        // Display components
        'badge' => ['intent' => 'neutral', 'size' => 'md', 'surface' => 'soft'],
        'step-marker' => ['intent' => 'neutral', 'size' => 'md'],
        'card' => ['variant' => 'outlined'],
        'card.header' => [],
        'card.body' => [],
        'card.footer' => [],
        'avatar' => ['size' => 'md', 'shape' => 'circle'],
        'avatar.group' => [],
        'alert' => ['variant' => 'info'],
        'toggle' => ['size' => 'md'],
        'checkbox' => ['size' => 'md', 'variant' => 'default'],
        'radio' => ['size' => 'md', 'variant' => 'default'],
        'field' => [],
        'field.set' => [],
        'field.legend' => [],

        // Data display components
        'table' => ['striped' => false, 'hoverable' => false, 'compact' => false, 'responsive' => true],
        'table.head' => [],
        'table.body' => [],
        'table.foot' => [],
        'table.row' => [],
        'table.th' => [],
        'table.td' => [],
        'pagination' => ['variant' => 'full'],
        'empty-state' => ['variant' => 'default'],
        'progress' => ['variant' => 'accent', 'size' => 'md'],
        'usage-meter' => ['warn' => 0.8, 'danger' => 1.0],
        'filter-builder' => ['searchable' => false, 'search-placeholder' => 'Search…', 'add-label' => 'Add filter'],
        'status-matrix' => ['cell-type' => 'status', 'legend' => true],
        'notification-center' => ['group-by' => 'none', 'filters' => false],
        'data-table' => ['density' => 'comfortable', 'selectable' => false, 'searchable' => false],
        'event-calendar' => ['view' => 'month', 'week-starts-on' => 1],
        'calendar' => ['week-starts-on' => 1],
        'map' => ['provider' => 'maplibre', 'zoom' => 2, 'highlight' => 'ring', 'highlight-color' => 'accent'],
        'stat' => ['animate' => false],

        // Layout primitives. These read a config default but were absent from the
        // stub, so the surface they expose was invisible to anyone reading their
        // own published file. Values here mirror the in-Blade fallback
        // exactly — declaring them changes nothing, it only makes them settable.
        'bento-grid' => ['gap' => 'md'],
        'brand-bar' => ['max' => 'xl'],
        'container' => ['max' => 'xl', 'padding' => 'md'],
        'feature-grid' => ['cols' => '1 sm:2 lg:3'],
        'footer' => ['max' => 'xl'],
        'grid' => ['cols' => 1, 'gap' => 'md'],
        'main' => ['padding' => 'lg', 'max' => '2xl'],
        'row' => ['gap' => 'md'],
        'section' => ['padding' => 'xl'],
        'stack' => ['gap' => 'md'],

        // Display components in the same position.
        'message' => ['actions-reveal' => 'hover'],
        'radial-progress' => ['size' => 'md', 'intent' => 'primary'],

        // Region labels — the accessible name of each group. Null keeps the
        // translated default (via __()); set one here to name the region once
        // app-wide instead of repeating `label` on every instance.
        'faq' => ['label' => null],
        'pricing-table' => ['label' => null],
        'testimonial-grid' => ['label' => null],
        'attachment-group' => ['label' => null],
        'skeleton' => ['animation' => 'shimmer'],
        'spinner' => ['size' => 'md', 'intent' => null],
        'assistant-message' => ['announce' => 'sentence'],
        'conversation' => ['max-height' => '24rem'],
        'scroll-area' => ['fade' => false],
        'shimmer' => ['active' => true, 'duration' => null],

        // Overlay components
        'dropdown' => ['placement' => 'bottom-start', 'offset' => 8],
        'dropdown.panel' => ['width' => 'auto'],
        'dropdown.item' => [],
        'dropdown.checkbox-item' => [],
        'dropdown.radio-item' => [],
        'tooltip' => ['placement' => 'top', 'offset' => 6, 'delay-show' => 300, 'delay-hide' => 100],
        'modal' => ['size' => 'md', 'dismissible' => true],
        'drawer' => ['position' => 'right', 'size' => 'md', 'dismissible' => true],

        // Navigation components
        'tabs' => ['variant' => 'underline'],
        'breadcrumb' => ['separator' => 'chevron'],
        'accordion' => ['mode' => 'single', 'variant' => 'bordered', 'size' => 'md'],
        'accordion.item' => [],
        'collapsible' => [],
        'sidebar' => [],
        'sidebar.group' => [],
        'sidebar.item' => [],
        'stepper' => ['orientation' => 'horizontal'],

        // Advanced form components
        'date-picker' => ['size' => 'md', 'format' => 'Y-m-d'],
        'file-upload' => ['size' => 'md', 'multiple' => false, 'accept' => null],
        'combobox' => ['size' => 'md', 'placeholder' => 'Select...'],
        'slider' => ['size' => 'md', 'min' => 0, 'max' => 100, 'step' => 1],
        'range-slider' => ['show_values' => true],
        'color-picker' => ['size' => 'md', 'format' => 'hex', 'native-on-mobile' => false],
        'number-input' => ['size' => 'md'],
        'password-input' => ['size' => 'md'],
        'time-picker' => ['size' => 'md'],

        // Additional components
        'navbar' => ['variant' => 'default'],
        'rating' => ['size' => 'md'],
        'callout' => ['variant' => 'info'],
        'data-list' => ['layout' => 'horizontal'],
        'segmented-control' => ['size' => 'md'],
        'popover' => ['placement' => 'bottom', 'offset' => 8],
        'scroll-to-top' => ['size' => 'md'],
        'alert-dialog' => ['dismissible' => false],
        'toast-region' => ['position' => 'top-right', 'duration' => 5000, 'max' => 5],

        // Activity-row kind → dot-color token map. Merged over the
        // component's built-in defaults (commit / merge / deploy / comment /
        // system / user); add your own kinds here, e.g.
        // 'release' => 'var(--color-wk-warning)'.
        'activity-row' => ['kinds' => []],
        'price' => ['size' => 'md'],
        'ticker' => ['size' => 'md'],

        // Feature card icon-chip tone + size
        'feature' => ['tone' => 'accent', 'size' => 'md'],

        // Reveal animation wrapper — defaults for `<x-wirekit::reveal>`
        'reveal' => [
            'preset' => 'fade-in',
            'trigger' => 'viewport',
            'duration' => 'normal',
            'once' => true,
            'threshold' => 0.4,
        ],
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
    | WireKit uses a shared semantic icon vocabulary (e.g. 'close', 'search',
    | 'chevron-down') resolved via presets to actual Blade Icon identifiers.
    | `php artisan wirekit:icons` prints the set your configured presets provide.
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
    |   entries override earlier ones; developer 'aliases' override all presets.
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
    |   'chartjs'    — Chart.js (MIT, ~60KB, requires npm install chart.js)
    |   'apexcharts' — ApexCharts (NOT MIT — see apex_license below)
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
    | apex_license records which ApexCharts tier you are on: 'community',
    | 'commercial' or 'oem'. It changes nothing at runtime — it is a declaration,
    | read only by `wirekit:doctor`, which reminds you that ApexCharts is not MIT.
    |
    | The key is listed here because the doctor tells you to set it in this file,
    | and an instruction that points at a key the shipped file does not contain
    | is one a reader cannot follow.
    |
    | Declaring 'community' does not stop the reminder, and that is deliberate:
    | the $2M USD revenue threshold is a continuing condition rather than an
    | install step, so a project passes it without any file here changing.
    |
    */

    'charts' => [
        'library' => null, // null = disabled, 'chartjs' = Chart.js, 'apexcharts' = ApexCharts
        'apex_license' => null, // null | 'community' | 'commercial' | 'oem' — declaration only
    ],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Bundle
    |--------------------------------------------------------------------------
    |
    | Choose which JavaScript bundle @wirekitScripts loads.
    |
    | 'full' — All Alpine components including overlays (~59 KB gzip)
    |          Includes Floating UI + focus-trap, bundled.
    |          Current measured sizes: docs.wirekit.app/dependencies.
    |
    | 'core' — Only chart Alpine component (~4 KB gzip)
    |          For projects that only use form components + charts.
    |
    | 'csp'  — For a Content-Security-Policy without `script-src 'unsafe-eval'`.
    |
    |          Alpine's default build evaluates every directive by constructing
    |          a function at runtime, which such a policy blocks. Nothing throws
    |          when it does: a one-time-code field still accepts typing and
    |          simply stops advancing to the next box. This bundle is built
    |          against Alpine's CSP distribution, which parses and interprets
    |          expressions instead.
    |
    |          ONE DIFFERENCE THAT MATTERS: unlike 'full' and 'core', this
    |          bundle CONTAINS Alpine and starts it. Do not also load your own —
    |          two Alpines on a page is its own class of bug.
    |
    |          Every directive in the catalog runs under this bundle. An audit
    |          drives Alpine's own parser and evaluator over each one on every
    |          build, and the budget for failures is zero.
    |
    |          WHAT IT DOES NOT COVER: Livewire brings its own directives and
    |          compiles them at runtime the same way. Livewire ships a CSP
    |          distribution for that — without it the page still needs
    |          'unsafe-eval', whichever WireKit bundle you load.
    |
    | An unrecognized value falls back to 'full' rather than to no script at all.
    |
    */

    'scripts' => [
        'bundle' => 'full', // 'full', 'core' or 'csp'

        /*
         * Load the ApexCharts adapter alongside the bundle above.
         *
         * Turn this on if you use <x-wirekit::chart library="apexcharts">. It is off by
         * default so an app that draws no charts ships no chart code — but leaving it off
         * while using the component fails in a way that is easy to lose time to: the page
         * renders, the console says "wirekitApexChart is not defined", and only the chart
         * is missing. You still supply ApexCharts itself; this loads WireKit's glue for it.
         */
        'apex' => false,
    ],

];
