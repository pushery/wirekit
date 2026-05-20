<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Support\Facades\File;
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
            'field' => ['category' => 'Form', 'description' => 'Form field wrapper with label, hint, and error'],
            'file-upload' => ['category' => 'Form', 'description' => 'Drag-and-drop file upload area'],
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
            'visually-hidden' => ['category' => 'Layout', 'description' => 'Screen-reader-only content wrapper'],

            // ── Typography ──
            'blockquote' => ['category' => 'Typography', 'description' => 'Styled blockquote with optional citation'],
            'code' => ['category' => 'Typography', 'description' => 'Inline code with monospace font'],
            'code-block' => ['category' => 'Typography', 'description' => 'Multi-line code block with copy button'],
            'heading' => ['category' => 'Typography', 'description' => 'Semantic heading (h1–h6) with auto-sizing'],
            'highlight' => ['category' => 'Typography', 'description' => 'Text with highlighted query matches'],
            'kbd' => ['category' => 'Typography', 'description' => 'Keyboard key indicator'],
            'link' => ['category' => 'Typography', 'description' => 'Styled anchor with external link support'],
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
            'alert' => ['category' => 'Display', 'description' => 'Contextual alert message'],
            'avatar' => ['category' => 'Display', 'description' => 'User avatar with image, initials, or status'],
            'badge' => ['category' => 'Display', 'description' => 'Small status label'],
            'button' => ['category' => 'Display', 'description' => 'Action button with variants and loading state'],
            'calendar' => ['category' => 'Display', 'description' => 'Calendar date display'],
            'callout' => ['category' => 'Display', 'description' => 'Highlighted information callout'],
            'card' => ['category' => 'Display', 'description' => 'Content container card with optional link'],
            'carousel' => ['category' => 'Display', 'description' => 'Image/content carousel with autoplay'],
            'clipboard-button' => ['category' => 'Display', 'description' => 'Copy-to-clipboard button'],
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
            'chart-mixed' => ['category' => 'Display', 'description' => 'Multi-type / multi-axis chart (per-dataset type field, dual y-axis)'],
            'sparkline' => ['category' => 'Display', 'description' => 'Inline trend sparkline (axis-less line/area chart)'],
            'spine-aware' => ['category' => 'Layout', 'description' => 'Opt-in wrapper that joins the page-edge content spine via WireKit::spinePadding()'],
            'stat' => ['category' => 'Display', 'description' => 'Single statistic display'],
            'stats' => ['category' => 'Display', 'description' => 'Statistics group container'],
            'table' => ['category' => 'Display', 'description' => 'Data table with sorting and styling options'],
            'ticker' => ['category' => 'Display', 'description' => 'Compact label + value + delta widget for dashboards'],
            'timeline' => ['category' => 'Display', 'description' => 'Vertical timeline of events'],
            'tree-view' => ['category' => 'Display', 'description' => 'Hierarchical tree view'],

            // ── Blueprint Primitives ──
            'date-separator' => ['category' => 'Display', 'description' => 'Horizontal divider with centered date label'],
            'kanban' => ['category' => 'Display', 'description' => 'Horizontal scrollable board of status columns'],
            'kanban-column' => ['category' => 'Display', 'description' => 'Single column within a kanban board'],
            'message' => ['category' => 'Display', 'description' => 'Chat/comment message bubble with author and timestamp'],
            'price' => ['category' => 'Display', 'description' => 'Locale-aware currency display with optional discount'],
            'reaction' => ['category' => 'Display', 'description' => 'Emoji reaction pill with count and toggle state'],
            'reveal' => ['category' => 'Display', 'description' => 'Animation wrapper — viewport / click / manual triggers, 11 in/out presets'],
            'toolbar' => ['category' => 'Navigation', 'description' => 'Horizontal bar with search, filters, and action buttons'],

            // ── System ──
            'chart' => ['category' => 'System', 'description' => 'Chart.js wrapper component'],
            'fonts' => ['category' => 'System', 'description' => 'GDPR-compliant font loader'],
            'glass' => ['category' => 'System', 'description' => 'Liquid Glass glassmorphism extension'],
            'icon' => ['category' => 'System', 'description' => 'SVG icon with preset support'],
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
     * Extract props from a component's blade file by parsing the @props directive.
     *
     * Returns the structured shape emitted by `PropsParser::parseBlade()`:
     * a list of records with `name`, `default`, `default_normalized`,
     * `type_hint`, and `comment` fields. **Breaking change in v2.0.0** —
     * the prior return shape (flat name→default-string map) is gone.
     * Developers reading the old shape must migrate; the new fields make
     * inline-comment metadata available without source-grepping AND
     * close two data-corruption bug classes (truncated `config(...)`
     * defaults, leaked inline comments) that the prior regex parser
     * silently shipped.
     *
     * @return list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string}>
     */
    public static function extractProps(string $name): array
    {
        return PropsParser::parseBlade(self::bladeFilePath($name));
    }

    /**
     * Get the blade file path for a component.
     */
    private static function bladeFilePath(string $name): string
    {
        return __DIR__.'/../resources/views/components/'.$name.'.blade.php';
    }
}
