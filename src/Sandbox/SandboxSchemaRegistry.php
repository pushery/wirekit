<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * In-memory registry of per-component sandbox schemas.
 *
 * A sandbox schema is an array of shape:
 *   [
 *     '<prop-name>' => [
 *       'type' => 'string|int|bool|array|mixed',
 *       'required' => false,
 *       'default' => '...',
 *       'allowed_values' => [...]   // optional enum
 *     ],
 *     ...
 *   ]
 *
 * Every component that the sandbox can render MUST register its schema
 * here. The `ComponentAllowlist` consults this registry to decide
 * whether a component name is sandbox-renderable.
 *
 * Schemas are seeded by `SandboxSchemaRegistry::seed()` (called once at
 * boot time) — see `Pushery\WireKit\WireKitServiceProvider::boot()`.
 *
 * Initial coverage ( starter set):
 *   - button, badge, callout, alert, card, code, code-block, kbd
 *   - heading, text, link
 *
 * Full coverage of all 109 components is deferred to a follow-up
 * plan; the renderer is functional with whatever schemas are seeded.
 * Anti-drift test fails the build if a registered schema references
 * a prop not in the component's `@props([...])` block.
 */
final class SandboxSchemaRegistry
{
    /**
     * @var array<string, array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>>
     */
    private static array $schemas = [];

    private static bool $seeded = false;

    /**
     * @param  array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>  $schema
     */
    public static function register(string $name, array $schema): void
    {
        self::$schemas[$name] = $schema;
    }

    public static function has(string $name): bool
    {
        self::ensureSeeded();

        return isset(self::$schemas[$name]);
    }

    /**
     * @return array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>|null
     */
    public static function get(string $name): ?array
    {
        self::ensureSeeded();

        return self::$schemas[$name] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        self::ensureSeeded();

        return array_keys(self::$schemas);
    }

    /**
     * Reset the registry — used in tests.
     */
    public static function flush(): void
    {
        self::$schemas = [];
        self::$seeded = false;
    }

    private static function ensureSeeded(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;
        self::seed();
    }

    private static function seed(): void
    {
        $variantValues = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'neutral', 'accent'];

        self::register('button', [
            'variant' => ['type' => 'string', 'default' => 'primary', 'allowed_values' => $variantValues],
            'size' => ['type' => 'string', 'default' => 'md', 'allowed_values' => ['xs', 'sm', 'md', 'lg', 'xl']],
            'type' => ['type' => 'string', 'default' => 'button', 'allowed_values' => ['button', 'submit', 'reset']],
            'disabled' => ['type' => 'bool', 'default' => false],
            // `body` is the SandboxRenderer convention for slot content —
            // `<x-wirekit::button>{body}</x-wirekit::button>`. Earlier
            // iterations used `label` here, which the renderer treated as
            // an attribute and never inserted into the slot, producing an
            // empty button under live preview.
            'body' => ['type' => 'string', 'default' => 'Click me'],
        ]);

        self::register('badge', [
            'variant' => ['type' => 'string', 'default' => 'neutral', 'allowed_values' => $variantValues],
            'size' => ['type' => 'string', 'default' => 'md', 'allowed_values' => ['sm', 'md', 'lg']],
            'dot' => ['type' => 'bool', 'default' => false],
            // See note on button.body — same slot-content convention.
            'body' => ['type' => 'string', 'default' => 'Badge'],
        ]);

        // Callout `title` is a NAMED SLOT (`@isset($title)` in
        // callout.blade.php), not a `@props` entry — the SandboxRenderer
        // emits string props as HTML attributes, so a `title` schema entry
        // would silently render as `<aside title="Note">` instead of
        // populating the slot. Named-slot support is a future renderer
        // feature; until then the title is intentionally not addressable.
        // Note: Alert.title IS a real `@props` (different shape) — its
        // schema below correctly carries the title prop.
        self::register('callout', [
            'variant' => ['type' => 'string', 'default' => 'info', 'allowed_values' => $variantValues],
            'body' => ['type' => 'string', 'default' => 'Callout body'],
        ]);

        self::register('alert', [
            'variant' => ['type' => 'string', 'default' => 'info', 'allowed_values' => $variantValues],
            'title' => ['type' => 'string', 'default' => 'Heads up'],
            'body' => ['type' => 'string', 'default' => 'Alert body'],
            'dismissible' => ['type' => 'bool', 'default' => false],
        ]);

        // The Card primitive accepts `variant` (outlined / elevated / flat),
        // NOT separate padded / bordered / elevated booleans — earlier schema
        // iterations declared three booleans that the Card component never
        // read, producing a no-padding bare-pill render in both static and
        // live sandbox modes. Card body content also requires the
        // `<x-wirekit::card.body>` sub-component for proper padding; the
        // SandboxRenderer's BODY_WRAPPERS map handles that wrap automatically
        // for this schema entry.
        self::register('card', [
            'variant' => ['type' => 'string', 'default' => 'outlined', 'allowed_values' => ['outlined', 'elevated', 'flat']],
            'body' => ['type' => 'string', 'default' => 'Card body'],
        ]);

        self::register('code', [
            'body' => ['type' => 'string', 'default' => '$value'],
        ]);

        self::register('code-block', [
            // `language` is a hint for consumer-side syntax highlighters
            // (Prism / Shiki / highlight.js). The component itself does NOT
            // ship a highlighter — it just emits `data-language` for consumers
            // to pick up. The allowed_values list mirrors the highlighter
            // grammars most consumers actually wire (matches the highlight.js
            // common bundle). Keeps the docs Sandbox surface beginner-discoverable
            // by rendering a `<select>` instead of a free-text input.
            'language' => [
                'type' => 'string',
                'default' => 'php',
                'allowed_values' => [
                    'bash', 'shell', 'plaintext',
                    'php', 'blade',
                    'html', 'xml',
                    'css', 'scss',
                    'javascript', 'typescript', 'json',
                    'python', 'ruby', 'go', 'rust',
                    'sql', 'yaml', 'markdown', 'dockerfile',
                ],
            ],
            'body' => ['type' => 'string', 'default' => "<?php\necho 'hello';"],
        ]);

        self::register('kbd', [
            'body' => ['type' => 'string', 'default' => 'Cmd'],
        ]);

        self::register('heading', [
            'level' => ['type' => 'int', 'default' => 2, 'allowed_values' => [1, 2, 3, 4, 5, 6]],
            'body' => ['type' => 'string', 'default' => 'Heading'],
        ]);

        // Text uses a string `variant` (default/muted/subtle/accent/success/
        // warning/danger), NOT a `muted` boolean. Earlier schema declared
        // `muted: bool` which the renderer emitted as `muted="false"` on the
        // `<p>` tag — silently no-op. Replaced with the real prop.
        self::register('text', [
            'size' => ['type' => 'string', 'default' => 'base', 'allowed_values' => ['xs', 'sm', 'base', 'lg', 'xl']],
            'variant' => ['type' => 'string', 'default' => 'default', 'allowed_values' => ['default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger']],
            'body' => ['type' => 'string', 'default' => 'Text body'],
        ]);

        self::register('link', [
            'href' => ['type' => 'string', 'default' => '#'],
            'body' => ['type' => 'string', 'default' => 'Link'],
        ]);
    }
}
