<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

use InvalidArgumentException;

/**
 * Single source of truth for WireKit theme presets.
 *
 * Every CLI surface that touches presets (ThemeCommand, InstallCommand,
 * ExportApiMapCommand, future MCP server queries) reads from THIS class.
 * Drift between the lists those commands print becomes unrepresentable —
 * there is exactly one definition.
 *
 * Schema per preset:
 *  - `label`     — human-readable display name (Title Case).
 *  - `vars`      — `:root {}` CSS variable overrides applied in light mode.
 *                  Empty string for `default` (bundled-values, no override).
 *  - `dark_vars` — `.dark` block overrides applied in dark mode (optional;
 *                  null when the preset does not differentiate dark mode).
 *
 * Extension #2: the `register()` static lets downstream packages (e.g.
 * a "FintechKit" preset bundle layered on top of WireKit) add their own
 * presets at runtime via a service-provider boot hook. Bundled presets
 * are loaded lazily; registered presets stack on top.
 */
final class ThemePresetRegistry
{
    /** @var array<string, array{label: string, vars: string, dark_vars: ?string}> */
    private static array $registered = [];

    /**
     * Bundled presets — the canonical seven that ship with WireKit.
     *
     * The `default` preset represents "stay on the bundled values" — its
     * `vars` is the empty string, signalling that applying it should
     * REMOVE any existing preset block from the developer's app.css and
     * return to the bundled token values.
     *
     * @return array<string, array{label: string, vars: string, dark_vars: ?string}>
     */
    private static function bundled(): array
    {
        return [
            'default' => [
                'label' => 'Default',
                'vars' => '',
                'dark_vars' => null,
            ],
            'minimal' => [
                'label' => 'Minimal',
                'vars' => <<<'CSS'
    /* Minimal — clean, borderless aesthetic */
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
    --radius-wk-full: 0px;
    --ring-wk-width: 2px;
    --shadow-wk-sm: none;
    --shadow-wk-md: none;
    --shadow-wk-lg: none;
CSS,
                'dark_vars' => null,
            ],
            'soft' => [
                'label' => 'Soft',
                'vars' => <<<'CSS'
    /* Soft — rounded, gentle shadows */
    --radius-wk-sm: 0.5rem;
    --radius-wk-md: 0.75rem;
    --radius-wk-lg: 1rem;
    --radius-wk-xl: 1.5rem;
    --color-wk-accent: oklch(0.541 0.281 293.009);
CSS,
                'dark_vars' => null,
            ],
            'material' => [
                'label' => 'Material',
                'vars' => <<<'CSS'
    /* Material — Google Material Design 3 inspired */
    --radius-wk-sm: 0.25rem;
    --radius-wk-md: 0.5rem;
    --radius-wk-lg: 0.75rem;
    --color-wk-accent: oklch(0.457 0.24 277.023);
    --transition-wk-easing: cubic-bezier(0, 0, 0.2, 1);
CSS,
                'dark_vars' => null,
            ],
            'brutalist' => [
                'label' => 'Brutalist',
                'vars' => <<<'CSS'
    /* Brutalist — bold borders, no shadows */
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
    --border-wk-width: 2px;
    --shadow-wk-sm: none;
    --shadow-wk-md: none;
    --shadow-wk-lg: none;
CSS,
                'dark_vars' => null,
            ],
            'retro-terminal' => [
                'label' => 'Retro Terminal',
                'vars' => <<<'CSS'
    /* Retro Terminal — green-on-black hacker aesthetic */
    --color-wk-accent: oklch(0.723 0.219 149.579);
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
CSS,
                'dark_vars' => null,
            ],
            'cupertino' => [
                'label' => 'Cupertino',
                'vars' => <<<'CSS'
    /* Cupertino — Apple HIG inspired */
    --radius-wk-sm: 0.375rem;
    --radius-wk-md: 0.625rem;
    --radius-wk-lg: 0.875rem;
    --radius-wk-xl: 1.75rem;
    --color-wk-accent: oklch(0.546 0.245 262.881);
    --transition-wk-easing: cubic-bezier(0, 0, 0.58, 1);
CSS,
                'dark_vars' => null,
            ],
        ];
    }

    /**
     * Return every preset (bundled + runtime-registered) keyed by slug.
     *
     * @return array<string, array{label: string, vars: string, dark_vars: ?string}>
     */
    public static function all(): array
    {
        return array_merge(self::bundled(), self::$registered);
    }

    /**
     * Lookup a single preset by slug. Returns null when the slug is unknown.
     *
     * @return array{label: string, vars: string, dark_vars: ?string}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * `default` is the special "remove any existing preset block" preset.
     * Callers that need to branch on the no-op semantic can ask without
     * matching against the literal string.
     */
    public static function isDefault(string $key): bool
    {
        return $key === 'default';
    }

    /**
     * Extension #2 — Register a custom preset at runtime.
     *
     * Intended call shape from a downstream service provider's boot()
     * method, e.g. for a "FintechKit" package layered on top:
     *
     *     ThemePresetRegistry::register('fintech', [
     *         'label' => 'FintechKit',
     *         'vars' => "    --color-wk-accent: oklch(0.65 0.22 220);",
     *         'dark_vars' => null,
     *     ]);
     *
     * After registration, the preset shows up in `keys()`, `all()`,
     * `wirekit:theme fintech` succeeds, and `wirekit:install --preset=fintech`
     * works. Re-registering an existing slug REPLACES the entry — useful
     * for testing.
     *
     * @param  array{label: string, vars: string, dark_vars?: ?string}  $preset
     *
     * @throws InvalidArgumentException when the slug is empty or the
     *                                  preset shape is incomplete.
     */
    public static function register(string $key, array $preset): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Preset key must be a non-empty string.');
        }
        if (! isset($preset['label']) || ! is_string($preset['label']) || $preset['label'] === '') {
            throw new InvalidArgumentException("Preset '{$key}' requires a non-empty 'label' string.");
        }
        if (! array_key_exists('vars', $preset) || ! is_string($preset['vars'])) {
            throw new InvalidArgumentException("Preset '{$key}' requires a 'vars' string (may be empty).");
        }

        self::$registered[$key] = [
            'label' => $preset['label'],
            'vars' => $preset['vars'],
            'dark_vars' => $preset['dark_vars'] ?? null,
        ];
    }

    /**
     * Clear runtime-registered presets. Reserved for tests — production
     * code should not call this.
     */
    public static function flushRegistered(): void
    {
        self::$registered = [];
    }
}
