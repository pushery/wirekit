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
            'editor' => ['category' => 'Form', 'description' => 'Rich-text editor (ProseMirror-based peer-dependency adapter; Tiptap recommended) with toolbar + hidden-input form binding'],
            'field' => ['category' => 'Form', 'description' => 'Form field wrapper with label, hint, and error'],
            'file-upload' => ['category' => 'Form', 'description' => 'Drag-and-drop file upload area'],
            'filter-builder' => ['category' => 'Form', 'description' => 'Active-filter chip bar with a typed add/edit popover (field/operator/value)'],
            'form' => ['category' => 'Form', 'description' => 'Form wrapper that inherits an error-announcement policy to every control inside'],
            'input' => ['category' => 'Form', 'description' => 'Text input with prefix/suffix support'],
            'label' => ['category' => 'Form', 'description' => 'Form label with required indicator'],
            'multi-select' => ['category' => 'Form', 'description' => 'Multi-value select with tag display'],
            'number-input' => ['category' => 'Form', 'description' => 'Numeric input with stepper buttons'],
            'otp-input' => ['category' => 'Form', 'description' => 'One-time password input with auto-focus'],
            'password-input' => ['category' => 'Form', 'description' => 'Password input with visibility toggle and strength meter'],
            'radial-progress' => ['category' => 'Display', 'description' => 'Radial progress ring — determinate, token-driven, with threshold coloring'],
            'radio' => ['category' => 'Form', 'description' => 'Radio button with label and hint'],
            'range-slider' => ['category' => 'Form', 'description' => 'Dual-thumb range slider'],
            'rating' => ['category' => 'Form', 'description' => 'Star rating input'],
            'select' => ['category' => 'Form', 'description' => 'Native select dropdown'],
            'slider' => ['category' => 'Form', 'description' => 'Single-thumb slider with value display'],
            'tags-input' => ['category' => 'Form', 'description' => 'Tag entry input with add/remove'],
            'textarea' => ['category' => 'Form', 'description' => 'Multi-line text input with resize control'],
            'time-picker' => ['category' => 'Form', 'description' => 'Time selection input'],
            'toggle' => ['category' => 'Form', 'description' => 'Toggle switch with label and hint'],
            'toggle-button' => ['category' => 'Display', 'description' => 'Single two-state button that stays pressed (aria-pressed) — the bold/italic/mute shape'],

            // ── Layout ──
            'app-shell' => ['category' => 'Layout', 'description' => 'App shell with header, sidebar, and main content'],
            'aspect-ratio' => ['category' => 'Layout', 'description' => 'Constrained aspect ratio wrapper'],
            'center' => ['category' => 'Layout', 'description' => 'Center content horizontally and vertically'],
            'container' => ['category' => 'Layout', 'description' => 'Max-width content container with optional padding'],
            'divider' => ['category' => 'Layout', 'description' => 'Horizontal or vertical divider with optional label'],
            'grid' => ['category' => 'Layout', 'description' => 'CSS grid with responsive column syntax'],
            'header' => ['category' => 'Layout', 'description' => 'Sticky page header for app-shell layouts'],
            'logo-cloud' => ['category' => 'Marketing', 'description' => 'Partner / customer logo wall, exposed as a labeled list'],
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
            'indicator' => ['category' => 'Display', 'description' => 'Anchors a count badge, status dot, or ribbon to any corner of any target — RTL-safe'],
            'kbd' => ['category' => 'Typography', 'description' => 'Keyboard key indicator'],
            'link' => ['category' => 'Typography', 'description' => 'Styled anchor with external link support'],
            'list' => ['category' => 'Typography', 'description' => 'Ordered or unordered list with type (disc / decimal / roman / alpha / none) and spacing variants'],
            'mark' => ['category' => 'Typography', 'description' => 'Highlighted text mark'],
            'prose' => ['category' => 'Typography', 'description' => 'Typography wrapper for raw HTML content'],
            'team-section' => ['category' => 'Marketing', 'description' => 'Team roster grid, exposed as a labeled list'],
            'team-member' => ['category' => 'Marketing', 'description' => 'One person in a team section — avatar, name, role, links'],
            'text' => ['category' => 'Typography', 'description' => 'Body text with size, variant, and weight'],

            // ── Navigation ──
            'bento-grid' => ['category' => 'Layout', 'description' => 'Asymmetric feature showcase — cells claim different amounts of space'],
            'bento-cell' => ['category' => 'Layout', 'description' => 'One cell of a bento grid; claims 1x1, 2x1, 1x2 or 2x2 from md up'],
            'bottom-nav' => ['category' => 'Navigation', 'description' => 'Fixed mobile tab bar — safe-area aware, 44px targets, aria-current'],
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
            'fab' => ['category' => 'Overlay', 'description' => 'Floating action button that fans out into secondary actions'],
            'hover-card' => ['category' => 'Overlay', 'description' => 'Content preview on hover'],
            'lightbox' => ['category' => 'Overlay', 'description' => 'Focus-trapped media viewer for images, video, and embeds — reusable, keyboard-navigable, event-driven'],
            'mockup' => ['category' => 'Marketing', 'description' => 'Frame chrome for a screenshot or live composition — browser, window, code, phone, tablet'],
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
            'announcement-banner' => ['category' => 'Marketing', 'description' => 'Dismissible page-edge announcement bar with persisted dismissal and an optional inline CTA'],
            'cta' => ['category' => 'Marketing', 'description' => 'Call-to-action banner section'],
            'faq' => ['category' => 'Marketing', 'description' => 'FAQ block — accordion of questions that emits FAQPage JSON-LD derived from what it rendered'],
            'faq-item' => ['category' => 'Marketing', 'description' => 'One question and answer inside an FAQ; records itself for the FAQPage schema'],
            'feature' => ['category' => 'Marketing', 'description' => 'Individual feature card for feature grids'],
            'feature-grid' => ['category' => 'Marketing', 'description' => 'Responsive grid for feature cards'],
            'footer' => ['category' => 'Layout', 'description' => 'Landing page footer with columns and legal'],
            'hero' => ['category' => 'Marketing', 'description' => 'Landing page hero with title, lede, and actions'],

            // ── Display ──
            'accordion' => ['category' => 'Display', 'description' => 'Collapsible content panels'],
            'action-bar' => ['category' => 'Display', 'description' => 'Floating action bar for bulk operations'],
            'activity-row' => ['category' => 'Display', 'description' => 'Activity-feed / timeline row with kind-colored dot, actor, timestamp, and badge slot'],
            'alert' => ['category' => 'Display', 'description' => 'Contextual alert message'],
            'attachment' => ['category' => 'Display', 'description' => 'File/image attachment card with formatted size, type label, upload state, and actions'],
            'attachment-group' => ['category' => 'Display', 'description' => 'Group of attachments — vertical stack or horizontal scroll-snap row'],
            'assistant-message' => ['category' => 'Display', 'description' => 'AI assistant turn — roles, streaming body, model chip, reasoning disclosure, and coalesced screen-reader announcements'],
            'avatar' => ['category' => 'Display', 'description' => 'User avatar with image, initials, or status'],
            'badge' => ['category' => 'Display', 'description' => 'Small status label'],
            'button' => ['category' => 'Display', 'description' => 'Action button with variants and loading state'],
            'button-group' => ['category' => 'Display', 'description' => 'Welds adjacent controls into one unit — collapsed inner radii and a single seam, RTL-safe'],
            'calendar' => ['category' => 'Display', 'description' => 'Calendar date display'],
            'callout' => ['category' => 'Display', 'description' => 'Highlighted information callout'],
            'card' => ['category' => 'Display', 'description' => 'Content container card with optional link'],
            'carousel' => ['category' => 'Display', 'description' => 'Image/content carousel with autoplay'],
            'chat-marker' => ['category' => 'Display', 'description' => 'In-thread meta row — streaming/tool status, system note, or labeled date break between chat messages'],
            'clipboard-button' => ['category' => 'Display', 'description' => 'Copy-to-clipboard button'],
            'collapsible' => ['category' => 'Display', 'description' => 'Single disclosure — one trigger toggles one collapsible region (WAI-ARIA Disclosure)'],
            'countdown' => ['category' => 'Display', 'description' => 'Live countdown to an absolute deadline with overdue + urgent states (client-side, no polling)'],
            'data-list' => ['category' => 'Display', 'description' => 'Key-value data display list'],
            'empty-state' => ['category' => 'Display', 'description' => 'Placeholder for empty content areas'],
            'image' => ['category' => 'Display', 'description' => 'Content image as a figure with alt text, lazy loading, CLS-safe ratio box, and optional caption'],
            'image-compare' => ['category' => 'Display', 'description' => 'Before/after image comparison slider'],
            'image-gallery' => ['category' => 'Display', 'description' => 'Responsive image grid with an accessible, keyboard-navigable lightbox'],
            'product-card' => ['category' => 'Display', 'description' => 'Ecommerce product card — image, price with compare-at, rating, stock state, CTA'],
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
            'shimmer' => ['category' => 'Display', 'description' => 'Animated text-glyph shimmer for live/streaming status (background-clip:text, Livewire :active-bindable)'],
            'chart-mixed' => ['category' => 'Display', 'description' => 'Multi-type / multi-axis chart (per-dataset type field, dual y-axis)'],
            'sparkline' => ['category' => 'Display', 'description' => 'Inline trend sparkline (axis-less line/area chart)'],
            'spine-aware' => ['category' => 'Layout', 'description' => 'Opt-in wrapper that joins the page-edge content spine via WireKit::spinePadding()'],
            'stage-card' => ['category' => 'Display', 'description' => 'Pipeline / kanban / roadmap stage card with intent left-stripe, count pill, and optional progress'],
            'stat' => ['category' => 'Display', 'description' => 'Single statistic display'],
            'stats' => ['category' => 'Display', 'description' => 'Statistics group container'],
            'status-matrix' => ['category' => 'Display', 'description' => '2D grid of typed status cells (tristate / toggle / status / heat) with sticky headers'],
            'status-tiles' => ['category' => 'Display', 'description' => 'N entities as colored status tiles, one glance — a fleet light with optional legend and colorblind-safe status icons'],
            'stream' => ['category' => 'Display', 'description' => 'Streaming text output (SSE) with correct live-region a11y, reduced-motion buffering, and defined abort / error states'],
            'swap' => ['category' => 'Display', 'description' => 'Two-state icon primitive — crossfade, rotate or flip between two children'],
            'table' => ['category' => 'Display', 'description' => 'Data table with sorting and styling options'],
            'ticker' => ['category' => 'Display', 'description' => 'Compact label + value + delta widget for dashboards'],
            'testimonial' => ['category' => 'Marketing', 'description' => 'Cited customer quote — figure/blockquote semantics, author, role, logo, optional rating'],
            'testimonial-grid' => ['category' => 'Marketing', 'description' => 'Responsive grid of testimonials, exposed as a labeled list'],
            'theme-controller' => ['category' => 'Layout', 'description' => 'Drop-in dark-mode control — button, switch or system/light/dark select'],
            'timeline' => ['category' => 'Display', 'description' => 'Vertical timeline of events'],
            'tree-view' => ['category' => 'Display', 'description' => 'Hierarchical tree view'],

            // ── Blueprint Primitives ──
            'conversation' => ['category' => 'Display', 'description' => 'Stick-to-bottom chat transcript scroller — follow-output, unread jump-to-latest, and history anchor-preserve'],
            'data-table' => ['category' => 'Display', 'description' => 'Client-mode data table with sort, search, selection, density, and column manager'],
            'date-separator' => ['category' => 'Display', 'description' => 'Horizontal divider with centered date label'],
            'event-calendar' => ['category' => 'Display', 'description' => 'Scheduling calendar with month, week, and agenda views'],
            'kanban' => ['category' => 'Display', 'description' => 'Horizontal scrollable board of status columns'],
            'kanban-column' => ['category' => 'Display', 'description' => 'Single column within a kanban board'],
            'message' => ['category' => 'Display', 'description' => 'Chat/comment message bubble with author, timestamp, and delivery status'],
            'message-typing' => ['category' => 'Display', 'description' => 'Animated three-dot typing indicator bubble for chat threads'],
            'notification-center' => ['category' => 'Display', 'description' => 'Bell trigger with unread badge and a grouped, realtime-capable notification panel'],
            'pricing-table' => ['category' => 'Marketing', 'description' => 'Responsive grid of pricing plans, exposed as a labeled list'],
            'pricing-tier' => ['category' => 'Marketing', 'description' => 'A single pricing plan — name, formatted amount, features, CTA, optional featured highlight'],
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
     * Every sub-component the package ships, keyed `parent.child`.
     *
     * Derived from the filesystem rather than hand-listed. A hand-maintained
     * list of 72 entries drifts the first time someone adds a file and forgets
     * the array; deriving it means the catalog cannot disagree with what ships.
     *
     * Sub-components are deliberately NOT in `all()`. They are part of the public
     * API — AGENTS.md tells an agent to reach for `card.body`, and `table.th`
     * carries its own documented props — but they are not components in the sense
     * every count in this project means by the word. Folding them in would turn
     * "173 components" into "245" overnight without a single new component
     * shipping. They are a separate surface, discoverable on their own terms.
     *
     * `index.blade.php` is excluded: that file IS the parent (the directory-
     * component form), not a child of it.
     *
     * @return array<string, array{parent: string, child: string}>
     */
    public static function subComponents(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $base = __DIR__.'/../resources/views/components/';
        $subs = [];

        foreach ((array) glob($base.'*', GLOB_ONLYDIR) as $dir) {
            $parent = basename((string) $dir);

            // A directory with no registry entry is not a component's sub-tree.
            if (! isset(self::all()[$parent])) {
                continue;
            }

            foreach ((array) glob($dir.'/*.blade.php') as $file) {
                $child = basename((string) $file, '.blade.php');

                if ($child === 'index') {
                    continue;
                }

                $subs[$parent.'.'.$child] = ['parent' => $parent, 'child' => $child];
            }
        }

        ksort($subs);

        return $cache = $subs;
    }

    /**
     * The sub-component names belonging to one parent, sorted.
     *
     * @return list<string>
     */
    public static function subComponentsOf(string $parent): array
    {
        return array_keys(array_filter(
            self::subComponents(),
            static fn (array $meta): bool => $meta['parent'] === $parent
        ));
    }

    /** Is this a `parent.child` name the package actually ships? */
    public static function isSubComponent(string $name): bool
    {
        return isset(self::subComponents()[$name]);
    }

    /**
     * Metadata for a name of EITHER kind — `card` or `card.body`.
     *
     * The three discovery surfaces (the show command, the JSON export, the MCP
     * catalog) each used to answer "what is card.body?" their own way, or not at
     * all: the MCP server returned null, which reads to an agent as "there is no
     * such component" — and it then writes content straight into <x-wirekit::card>,
     * the exact mistake AGENTS.md exists to prevent. One resolver, one answer.
     *
     * A sub-component inherits its parent's category (it belongs to the same part
     * of the library) and describes itself in terms of that parent, because that
     * is the only honest description available without hand-writing 72 of them.
     *
     * @return array{category: string, description: string, parent?: string}|null
     */
    public static function resolve(string $name): ?array
    {
        $top = self::get($name);

        if ($top !== null) {
            return $top;
        }

        $sub = self::subComponents()[$name] ?? null;

        if ($sub === null) {
            return null;
        }

        $parentMeta = self::get($sub['parent']);

        return [
            'category' => $parentMeta['category'] ?? 'Display',
            'description' => "The {$sub['child']} part of the {$sub['parent']} component",
            'parent' => $sub['parent'],
        ];
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
     *
     * Resolves the top-level `components/<name>.blade.php` first, and falls back
     * to the directory-component form `components/<name>/index.blade.php` when the
     * top-level file does not exist (e.g. `list` lives at `list/index.blade.php`).
     * Without this fallback the registry read that component's props from a
     * non-existent path and reported it with zero props. Mirrors the
     * dual enumeration ComponentRegistryTest already uses for membership.
     */
    private static function bladeFilePath(string $name): string
    {
        $base = __DIR__.'/../resources/views/components/';

        // `parent.child` → components/parent/child.blade.php. Without this the
        // props of every sub-component were unreachable: `table.th` has carried a
        // documented `headerScope` prop since 2.16.0 that no discovery surface
        // could report, because the path it looked at did not exist.
        if (str_contains($name, '.')) {
            [$parent, $child] = explode('.', $name, 2);
            $nested = $base.$parent.'/'.$child.'.blade.php';

            if (is_file($nested)) {
                return $nested;
            }
        }

        $topLevel = $base.$name.'.blade.php';
        if (is_file($topLevel)) {
            return $topLevel;
        }

        $indexed = $base.$name.'/index.blade.php';
        if (is_file($indexed)) {
            return $indexed;
        }

        // Neither exists — return the top-level path so downstream behavior
        // (PropsParser → []) is unchanged for a genuinely missing component.
        return $topLevel;
    }
}
