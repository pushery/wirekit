<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Support\Facades\File;

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
            'cta' => ['category' => 'Display', 'description' => 'Call-to-action banner section'],
            'feature' => ['category' => 'Display', 'description' => 'Individual feature card for feature grids'],
            'feature-grid' => ['category' => 'Display', 'description' => 'Responsive grid for feature cards'],
            'footer' => ['category' => 'Layout', 'description' => 'Landing page footer with columns and legal'],
            'hero' => ['category' => 'Display', 'description' => 'Landing page hero with title, lede, and actions'],

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
            'scroll-area' => ['category' => 'Display', 'description' => 'Scrollable content area with styled scrollbar'],
            'segmented-control' => ['category' => 'Display', 'description' => 'Segmented toggle control'],
            'skeleton' => ['category' => 'Display', 'description' => 'Loading placeholder skeleton'],
            'stat' => ['category' => 'Display', 'description' => 'Single statistic display'],
            'stats' => ['category' => 'Display', 'description' => 'Statistics group container'],
            'table' => ['category' => 'Display', 'description' => 'Data table with sorting and styling options'],
            'timeline' => ['category' => 'Display', 'description' => 'Vertical timeline of events'],
            'tree-view' => ['category' => 'Display', 'description' => 'Hierarchical tree view'],

            // ── System ──
            'chart' => ['category' => 'System', 'description' => 'Chart.js wrapper component'],
            'fonts' => ['category' => 'System', 'description' => 'GDPR-compliant font loader'],
            'glass' => ['category' => 'System', 'description' => 'Liquid Glass glassmorphism extension'],
            'icon' => ['category' => 'System', 'description' => 'SVG icon with preset support'],
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
     * @return array<string, mixed>
     */
    public static function extractProps(string $name): array
    {
        $path = self::bladeFilePath($name);

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if (! preg_match('/@props\(\[(.*?)\]\)/s', $content, $match)) {
            return [];
        }

        $props = [];
        // Match 'propName' => value or 'propName' patterns
        preg_match_all("/['\"]([^'\"]+)['\"]\s*=>\s*(.+?)(?=,\s*['\"]|\s*\])/s", $match[1], $propMatches, PREG_SET_ORDER);

        foreach ($propMatches as $propMatch) {
            $propName = $propMatch[1];
            $default = trim($propMatch[2]);
            // Clean trailing commas and whitespace
            $default = rtrim($default, ", \t\n\r");
            $props[$propName] = $default;
        }

        return $props;
    }

    /**
     * Get the blade file path for a component.
     */
    private static function bladeFilePath(string $name): string
    {
        return __DIR__.'/../resources/views/components/'.$name.'.blade.php';
    }
}
