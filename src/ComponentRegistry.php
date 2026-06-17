<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Support\Facades\File;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Support\ClassPropsExtractor;
use Pushery\WireKit\Support\PropsParser;

class ComponentRegistry
{
    /**
     * Component metadata registry. Every top-level component MUST have an entry.
     * Anti-drift tests enforce this — adding a blade file without a registry
     * entry will fail CI.
     *
     * @return array<string, array{category: string, description: string}>
     */
    public static function all(): array
    {
        return [
            // ── Form ──
            'checkbox' => ['category' => 'Form', 'description' => 'Checkbox input with label, hint, and error support'],
            'color-picker' => ['category' => 'Form', 'description' => 'Native color picker with preview swatch'],
            'combobox' => ['category' => 'Form', 'description' => 'Autocomplete select with search filtering'],
            'date-picker' => ['category' => 'Form', 'description' => 'Calendar-based date input'],
            'editor' => ['category' => 'Form', 'description' => 'Rich-text editor (Tiptap peer-dependency adapter) with toolbar + hidden-input form binding'],
            'field' => ['category' => 'Form', 'description' => 'Form field wrapper with label, hint, and error'],
            'file-upload' => ['category' => 'Form', 'description' => 'Drag-and-drop file upload area'],
            'filter-builder' => ['category' => 'Form', 'description' => 'Active-filter chip bar with a typed add/edit popover (field/operator/value)'],
            'input' => ['category' => 'Form', 'description' => 'Text input with prefix/suffix support'],
            'label' => ['category' => 'Form', 'description' => 'Form label with required indicator'],
            'multi-select' => ['category' => 'Form', 'description' => 'Multi-value select with tag display'],
            'number-input' => ['category' => 'Form', 'description' => 'Numeric input with stepper buttons'],
            'otp-input' => ['category' => 'Form', 'description' => 'One-time password input with auto-focus'],
            'password-input' => ['category' => 'Form', 'description' => 'Password input with visibility toggle and strength meter'],
            'radio' => ['category' => 'Form', 'description' => 'Radio button with label and hint'],
            'range-slider' => ['category' => 'Form', 'description' => 'Dual-thumb range slider'],
            'rating' => ['category' => 'Form', 'description' => 'Star rating input'],
            'select' => ['category' => 'Form', 'description' => 'Native select dropdown'],
            'slider' => ['category' => 'Form', 'description' => 'Single-thumb slider with value display'],
            'tags-input' => ['category' => 'Form', 'description' => 'Tag entry input with add/remove'],
            'textarea' => ['category' => 'Form', 'description' => 'Multi-line text input with resize control'],
            'time-picker' => ['category' => 'Form', 'description' => 'Time selection input'],
            'toggle' => ['category' => 'Form', 'description' => 'Toggle switch with label and hint'],

            // ── Layout ──
            'app-shell' => ['category' => 'Layout', 'description' => 'App shell with header, sidebar, and main content'],
            'aspect-ratio' => ['category' => 'Layout', 'description' => 'Constrained aspect ratio wrapper'],
            'center' => ['category' => 'Layout', 'description' => 'Center content horizontally and vertically'],
            'container' => ['category' => 'Layout', 'description' => 'Max-width content container with optional padding'],
            'divider' => ['category' => 'Layout', 'description' => 'Horizontal or vertical divider with optional label'],
            'grid' => ['category' => 'Layout', 'description' => 'CSS grid with responsive column syntax'],
            'header' => ['category' => 'Layout', 'description' => 'Sticky page header for app-shell layouts'],
            'main' => ['category' => 'Layout', 'description' => 'Primary content area in app-shell layouts'],
            'resizable' => ['category' => 'Layout', 'description' => 'Resizable panel layout'],
            'row' => ['category' => 'Layout', 'description' => 'Horizontal flex container'],
            'section' => ['category' => 'Layout', 'description' => 'Full-width page section with background and padding'],
            'spacer' => ['category' => 'Layout', 'description' => 'Flexible space filler in flex layouts'],
            'stack' => ['category' => 'Layout', 'description' => 'Vertical flex container'],
            'sticky-panel' => ['category' => 'Layout', 'description' => 'Sticky companion column (header / scrollable body / footer) pinned beside an article'],
            'skip-link' => ['category' => 'Layout', 'description' => 'WCAG 2.4.1 skip-link to main landmark (keyboard bypass)'],
            'visually-hidden' => ['category' => 'Layout', 'description' => 'Screen-reader-only content wrapper'],

            // ── Typography ──
            'blockquote' => ['category' => 'Typography', 'description' => 'Styled blockquote with optional citation'],
            'code' => ['category' => 'Typography', 'description' => 'Inline code with monospace font'],
            'code-block' => ['category' => 'Typography', 'description' => 'Multi-line code block with copy button'],
            'heading' => ['category' => 'Typography', 'description' => 'Semantic heading (h1–h6) with auto-sizing'],
            'highlight' => ['category' => 'Typography', 'description' => 'Text with highlighted query matches'],
            'kbd' => ['category' => 'Typography', 'description' => 'Keyboard key indicator'],
            'link' => ['category' => 'Typography', 'description' => 'Styled anchor with external link support'],
            'list' => ['category' => 'Typography', 'description' => 'Ordered or unordered list with type (disc / decimal / roman / alpha / none) and spacing variants'],
            'mark' => ['category' => 'Typography', 'description' => 'Highlighted text mark'],
            'prose' => ['category' => 'Typography', 'description' => 'Typography wrapper for raw HTML content'],
            'text' => ['category' => 'Typography', 'description' => 'Body text with size, variant, and weight'],

            // ── Navigation ──
            'brand' => ['category' => 'Navigation', 'description' => 'Logo and name combo for header/sidebar'],
            'brand-bar' => ['category' => 'Navigation', 'description' => 'Page-chrome wrapper for brand + tagline + actions with content-edge padding'],
            'breadcrumb' => ['category' => 'Navigation', 'description' => 'Breadcrumb trail with separator'],
            'menubar' => ['category' => 'Navigation', 'description' => 'Horizontal menu bar with dropdowns'],
            'navbar' => ['category' => 'Navigation', 'description' => 'Top navigation bar'],
            'navigation-menu' => ['category' => 'Navigation', 'description' => 'Accessible navigation menu with mega-menu support'],
            'pagination' => ['category' => 'Navigation', 'description' => 'Page navigation for paginated data'],
            'profile' => ['category' => 'Navigation', 'description' => 'Avatar and name display for headers'],
            'scroll-to-top' => ['category' => 'Navigation', 'description' => 'Floating scroll-to-top button'],
            'sidebar' => ['category' => 'Navigation', 'description' => 'Vertical sidebar navigation'],
            'stepper' => ['category' => 'Navigation', 'description' => 'Step indicator for multi-step flows'],
            'tabs' => ['category' => 'Navigation', 'description' => 'Tabbed content navigation'],

            // ── Overlay ──
            'alert-dialog' => ['category' => 'Overlay', 'description' => 'Confirmation dialog with required action'],
            'command-palette' => ['category' => 'Overlay', 'description' => 'Searchable command palette (⌘K)'],
            'context-menu' => ['category' => 'Overlay', 'description' => 'Right-click context menu'],
            'drawer' => ['category' => 'Overlay', 'description' => 'Slide-out panel from screen edge'],
            'dropdown' => ['category' => 'Overlay', 'description' => 'Dropdown menu with items'],
            'hover-card' => ['category' => 'Overlay', 'description' => 'Content preview on hover'],
            'modal' => ['category' => 'Overlay', 'description' => 'Dialog overlay with backdrop'],
            'popover' => ['category' => 'Overlay', 'description' => 'Contextual content popover'],
            'toast-region' => ['category' => 'Overlay', 'description' => 'Toast notification container'],
            'tooltip' => ['category' => 'Overlay', 'description' => 'Hover tooltip with configurable position'],
            'tour' => ['category' => 'Overlay', 'description' => 'Guided product tour with steps'],

            // ── Marketing ──
            // Four canonical conversion-focused primitives. `footer` stays
            // Layout (structural primitive used on any page); `reveal` stays
            // Display (generic animation primitive); `brand-bar` stays
            // Navigation (header chrome). The Marketing category is reserved
            // for components whose PRIMARY purpose is marketing / conversion
            // — narrow scope, clear semantic.
            'cta' => ['category' => 'Marketing', 'description' => 'Call-to-action banner section'],
            'feature' => ['category' => 'Marketing', 'description' => 'Individual feature card for feature grids'],
            'feature-grid' => ['category' => 'Marketing', 'description' => 'Responsive grid for feature cards'],
            'footer' => ['category' => 'Layout', 'description' => 'Landing page footer with columns and legal'],
            'hero' => ['category' => 'Marketing', 'description' => 'Landing page hero with title, lede, and actions'],

            // ── Display ──
            'accordion' => ['category' => 'Display', 'description' => 'Collapsible content panels'],
            'action-bar' => ['category' => 'Display', 'description' => 'Floating action bar for bulk operations'],
            'activity-row' => ['category' => 'Display', 'description' => 'Activity-feed / timeline row with kind-colored dot, actor, timestamp, and badge slot'],
            'alert' => ['category' => 'Display', 'description' => 'Contextual alert message'],
            'avatar' => ['category' => 'Display', 'description' => 'User avatar with image, initials, or status'],
            'badge' => ['category' => 'Display', 'description' => 'Small status label'],
            'button' => ['category' => 'Display', 'description' => 'Action button with variants and loading state'],
            'calendar' => ['category' => 'Display', 'description' => 'Calendar date display'],
            'callout' => ['category' => 'Display', 'description' => 'Highlighted information callout'],
            'card' => ['category' => 'Display', 'description' => 'Content container card with optional link'],
            'carousel' => ['category' => 'Display', 'description' => 'Image/content carousel with autoplay'],
            'clipboard-button' => ['category' => 'Display', 'description' => 'Copy-to-clipboard button'],
            'collapsible' => ['category' => 'Display', 'description' => 'Single disclosure — one trigger toggles one collapsible region (WAI-ARIA Disclosure)'],
            'data-list' => ['category' => 'Display', 'description' => 'Key-value data display list'],
            'empty-state' => ['category' => 'Display', 'description' => 'Placeholder for empty content areas'],
            'image-compare' => ['category' => 'Display', 'description' => 'Before/after image comparison slider'],
            'progress' => ['category' => 'Display', 'description' => 'Progress bar with optional value display'],
            'qr-code' => ['category' => 'Display', 'description' => 'QR code generator'],
            'reading-bookmark' => ['category' => 'Display', 'description' => 'Save / restore scroll position with return-prompt UX'],
            'reading-meta' => ['category' => 'Display', 'description' => 'Time-to-read estimate from word count, optional remaining-time + per-paragraph annotations'],
            'reading-minimap' => ['category' => 'Display', 'description' => 'Every-item density overview with click + drag-pan navigation'],
            'reading-progress' => ['category' => 'Display', 'description' => 'Viewport-pinned reading-progress indicator (bar or dot)'],
            'reading-shell' => ['category' => 'Display', 'description' => 'Composition wrapper with toggles + density preset'],
            'reading-spine' => ['category' => 'Display', 'description' => 'Sidebar mini-TOC tracking scroll, expanding on hover/focus'],
            'reading-toc' => ['category' => 'Display', 'description' => 'Horizontal sticky-strip TOC for marketing landing pages'],
            'replay-button' => ['category' => 'Display', 'description' => 'Re-mount the closest [data-replay-target] ancestor — companion to the `data-replayable` animation contract'],
            'scroll-area' => ['category' => 'Display', 'description' => 'Scrollable content area with styled scrollbar'],
            'segmented-control' => ['category' => 'Display', 'description' => 'Segmented toggle control'],
            'skeleton' => ['category' => 'Display', 'description' => 'Loading placeholder skeleton'],
            'spinner' => ['category' => 'Display', 'description' => 'Accessible loading spinner (size + semantic intent, role=status)'],
            'chart-mixed' => ['category' => 'Display', 'description' => 'Multi-type / multi-axis chart (per-dataset type field, dual y-axis)'],
            'sparkline' => ['category' => 'Display', 'description' => 'Inline trend sparkline (axis-less line/area chart)'],
            'spine-aware' => ['category' => 'Layout', 'description' => 'Opt-in wrapper that joins the page-edge content spine via WireKit::spinePadding()'],
            'stage-card' => ['category' => 'Display', 'description' => 'Pipeline / kanban / roadmap stage card with intent left-stripe, count pill, and optional progress'],
            'stat' => ['category' => 'Display', 'description' => 'Single statistic display'],
            'stats' => ['category' => 'Display', 'description' => 'Statistics group container'],
            'status-matrix' => ['category' => 'Display', 'description' => '2D grid of typed status cells (tristate / toggle / status / heat) with sticky headers'],
            'table' => ['category' => 'Display', 'description' => 'Data table with sorting and styling options'],
            'ticker' => ['category' => 'Display', 'description' => 'Compact label + value + delta widget for dashboards'],
            'timeline' => ['category' => 'Display', 'description' => 'Vertical timeline of events'],
            'tree-view' => ['category' => 'Display', 'description' => 'Hierarchical tree view'],

            // ── Blueprint Primitives ──
            'data-table' => ['category' => 'Display', 'description' => 'Client-mode data table with sort, search, selection, density, and column manager'],
            'date-separator' => ['category' => 'Display', 'description' => 'Horizontal divider with centered date label'],
            'event-calendar' => ['category' => 'Display', 'description' => 'Scheduling calendar with month, week, and agenda views'],
            'kanban' => ['category' => 'Display', 'description' => 'Horizontal scrollable board of status columns'],
            'kanban-column' => ['category' => 'Display', 'description' => 'Single column within a kanban board'],
            'message' => ['category' => 'Display', 'description' => 'Chat/comment message bubble with author and timestamp'],
            'notification-center' => ['category' => 'Display', 'description' => 'Bell trigger with unread badge and a grouped, realtime-capable notification panel'],
            'price' => ['category' => 'Display', 'description' => 'Locale-aware currency display with optional discount'],
            'reaction' => ['category' => 'Display', 'description' => 'Emoji reaction pill with count and toggle state'],
            'reveal' => ['category' => 'Display', 'description' => 'Animation wrapper — viewport / click / manual triggers, 11 in/out presets'],
            'toolbar' => ['category' => 'Navigation', 'description' => 'Horizontal bar with search, filters, and action buttons'],
            'usage-meter' => ['category' => 'Display', 'description' => 'Usage-vs-limit meter with threshold intent, panel grid, and plan-paywall gate'],

            // ── System ──
            'chart' => ['category' => 'System', 'description' => 'Chart.js wrapper component'],
            'fonts' => ['category' => 'System', 'description' => 'GDPR-compliant font loader'],
            'glass' => ['category' => 'System', 'description' => 'Liquid Glass glassmorphism extension'],
            'icon' => ['category' => 'System', 'description' => 'SVG icon with preset support'],
            'map' => ['category' => 'System', 'description' => 'Map adapter (MapLibre/Leaflet peer dependency) with markers and an accessible location list'],
            'structured-data' => ['category' => 'System', 'description' => 'JSON-LD script block emitter (XSS-safe via JSON_HEX_TAG)'],
        ];
    }

    /**
     * Get component metadata by name.
     *
     * @return array{category: string, description: string}|null
     */
    public static function get(string $name): ?array
    {
        return static::all()[$name] ?? null;
    }

    /**
     * Registered Blade-tag overrides for class-based components whose
     * tag form differs from the anonymous `<x-wirekit::name>` convention.
     *
     * The anonymous form (default) reads `<x-wirekit::{name}>`.
     * Class-based components registered via `loadViewComponentsAs(...)`
     * use the alternate form `<x-wirekit-{name}>` — Laravel's prefixed-
     * class-component shape. The two cannot be detected from
     * ComponentRegistry::all() metadata alone (both ship a Blade file
     * AND a PHP class); this map encodes the exception per component.
     *
     * Used by CLI surfaces (`wirekit:show`, `wirekit:list`,
     * `wirekit:export-json`, `wirekit:install summary`,
     * `wirekit:export-api-map`) so every "what tag do I use?" report
     * prints the WORKING form. Without this map, `wirekit:show chart`
     * previously printed `<x-wirekit::chart>` — which renders a 500
     * because the anonymous Blade file references an undefined
     * `$alpineComponent` variable (the class constructor populates it
     * via the class-based path).
     *
     * @var array<string, string>
     */
    private const TAG_OVERRIDES = [
        // Class-based via WireKitServiceProvider::registerComponents() —
        // the anonymous file at resources/views/components/chart.blade.php
        // exists but is meant to be reached via the class component, which
        // injects the `$alpineComponent` variable into the view context.
        'chart' => '<x-wirekit-chart>',
    ];

    /**
     * Return the canonical Blade tag form for a registered component.
     *
     * Defaults to the anonymous `<x-wirekit::name>` shape. Components
     * registered as class-based via `loadViewComponentsAs(...)` carry
     * an override in `TAG_OVERRIDES` and use the prefixed-class shape
     * (e.g. `<x-wirekit-chart>` for `chart`).
     *
     * The leading `<` and trailing `>` are part of the returned value —
     * callers concatenate-ready.
     */
    public static function tag(string $name): string
    {
        return self::TAG_OVERRIDES[$name] ?? "<x-wirekit::{$name}>";
    }

    /**
     * expose the deprecated tag-form
     * alias for class-based components so tool integrators can
     * verify both the canonical (single-hyphen) shape AND any
     * historical (double-colon) shape that documentation might have
     * shipped. Returns NULL for normal anonymous components (their
     * canonical = their alias).
     *
     * Used by ExportJsonCommand to emit a `tag_alias` field next to
     * `tag` so a downstream tool integrator who hard-coded the
     * double-colon form during the v2.0 → v2.2 migration window can
     * still grep against the schema and find the component.
     */
    public static function tagAlias(string $name): ?string
    {
        return isset(self::TAG_OVERRIDES[$name]) ? "<x-wirekit::{$name}>" : null;
    }

    /**
     * Get all components in a given category.
     *
     * @return array<string, array{category: string, description: string}>
     */
    public static function category(string $category): array
    {
        return array_filter(static::all(), fn ($meta) => $meta['category'] === $category);
    }

    /**
     * Get all unique category names.
     *
     * @return string[]
     */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(static::all(), 'category')));
    }

    /**
     * Extract props from a component. Two flavors coexist:
     *
     * 1. **Anonymous Blade component** (the 99% case): parse the
     *    `@props([...])` directive from the Blade file via PropsParser.
     * 2. **Class-based Blade component** (chart): walk the
     *    constructor signature via Reflection (ClassPropsExtractor).
     *
     * The class-based path is selected when `CLASS_COMPONENTS` carries
     * an entry mapping the component name → its class. Both paths
     * return the same shape so downstream callers don't branch.
     *
     * **Breaking change in v2.0.0** — the prior return shape (flat
     * name→default-string map) is gone. Developers reading the old
     * shape must migrate; the new fields make inline-comment metadata
     * available without source-grepping AND close two data-corruption
     * bug classes (truncated `config(...)` defaults, leaked inline
     * comments) that the prior regex parser silently shipped.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>}>
     */
    public static function extractProps(string $name): array
    {
        if (isset(self::CLASS_COMPONENTS[$name])) {
            return ClassPropsExtractor::extract(self::CLASS_COMPONENTS[$name]);
        }

        return PropsParser::parseBlade(self::bladeFilePath($name));
    }

    /**
     * Return the backing class FQCN for class-based components, or null for
     * anonymous Blade components. Used by the JSON-manifest exporter to
     * skip false-positive slot detection on class-side public properties.
     *
     * @return class-string|null
     */
    public static function componentClass(string $name): ?string
    {
        return self::CLASS_COMPONENTS[$name] ?? null;
    }

    /**
     * Class-based-component registry. Keyed by component name; value is
     * the FQCN of the class registered via `loadViewComponentsAs(...)`
     * in WireKitServiceProvider. Mirror of the
     * `loadViewComponentsAs('wirekit', [...])` array — kept in sync
     * manually because the service provider doesn't surface the list
     * as data.
     *
     * @var array<string, class-string>
     */
    private const CLASS_COMPONENTS = [
        'chart' => Chart::class,
    ];

    /**
     * Get the blade file path for a component.
     */
    private static function bladeFilePath(string $name): string
    {
        return __DIR__.'/../resources/views/components/'.$name.'.blade.php';
    }
}
