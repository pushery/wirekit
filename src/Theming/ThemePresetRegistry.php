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
     * Bundled presets — the canonical eight that ship with WireKit.
     *
     * The `default` preset represents "stay on the bundled values" — its
     * `vars` is the empty string, signaling that applying it should
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
    /* Co-tuned label color. A preset that recolors --color-wk-accent
       MUST also pin --color-wk-accent-fg, otherwise the default near-white
       fg pairs with the new accent by luck only. Near-white on this violet
       clears WCAG AA at 5.63:1. Pinning it here keeps the pair aligned in
       BOTH light and dark: `wirekit:theme` emits these via @theme → :root
       (specificity 0,1,0), which wins over the dark default's :where(.dark)
       accent-fg (specificity 0) in dark mode too, so accent + fg move
       together instead of the fg snapping to the dark-mode near-black. */
    --color-wk-accent-fg: oklch(0.985 0 0);
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
    /* Co-tuned label color — near-white clears WCAG AA at 7.73:1 on this
       indigo; pinned for both modes (see the `soft` preset for the why). */
    --color-wk-accent-fg: oklch(0.985 0 0);
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
    /* This green is LIGHT (L=0.723), so the default near-white label only
       reached 2.13:1 (WCAG fail) — a near-BLACK label is the correct pair,
       clearing 8.06:1. Pinned for both modes (see the `soft` preset). */
    --color-wk-accent-fg: oklch(0.205 0 0);
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
    /* Co-tuned label color — near-white clears WCAG AA at 5.03:1 on this
       blue; pinned for both modes (see the `soft` preset for the why). */
    --color-wk-accent-fg: oklch(0.985 0 0);
    --transition-wk-easing: cubic-bezier(0, 0, 0.58, 1);
CSS,
                'dark_vars' => null,
            ],
            'aurora' => [
                'label' => 'Aurora',
                'vars' => <<<'CSS'
    /* Aurora — modern, color-confident preset toned to the WireKit brand
       magenta (hue 306°, near #ab35ff from the official logo). Less
       monochrome than Default; more color-forward than Cupertino.

       Single-source hue: every hue-dependent token reads from --theme-hue
       (306 by default). Override --theme-hue in your own :root block AFTER
       applying this preset to retint the entire palette in ONE LINE:

           :root { --theme-hue: 264; }   (indigo Aurora)

       Scope: re-tones the brand / accent surface only. Semantic intents
       (success / warning / danger / info) intentionally inherit the WireKit
       default palette — they keep universal legibility regardless of preset.

       The two extra brand-decorative tokens (`--color-wk-accent-brand`,
       `--color-wk-accent-brand-fg`) preserve the brand-exact L=0.605 value
       for surfaces where body text never sits on top (logo backplate, hero
       glyphs). The interactive `--color-wk-accent` reads from L=0.55 — the
       WCAG-AA floor at any hue, so the accent-fg label clears AA (5.28:1
       light / 6.29:1 dark at the default hue 306). */

    --theme-hue: 306;

    --color-wk-accent: oklch(0.55 0.22 var(--theme-hue));
    --color-wk-accent-hover: oklch(0.49 0.22 var(--theme-hue));
    --color-wk-accent-content: oklch(0.42 0.22 var(--theme-hue));
    --color-wk-accent-fg: oklch(0.99 0.005 var(--theme-hue));

    /* Brand-exact tokens — for decorative use only. Pair with bg
       (no body text on top) to avoid the AA contrast trap. */
    --color-wk-accent-brand: oklch(0.605 0.27 var(--theme-hue));
    --color-wk-accent-brand-fg: oklch(0.99 0.005 var(--theme-hue));

    /* Same modest radii as Aurora — dashboard-tuned. */
    --radius-wk: 0.375rem;
    --radius-wk-sm: 0.25rem;
    --radius-wk-md: 0.375rem;
    --radius-wk-lg: 0.5rem;
    --radius-wk-xl: 0.75rem;

    --color-wk-bg: oklch(1 0 0);
    --color-wk-bg-elevated: oklch(1 0 0);
    --color-wk-bg-muted: oklch(0.972 0 0);
    --color-wk-bg-subtle: oklch(0.985 0 0);

    /* Foreground text — softened off the default pure-neutral near-black
       (oklch(0.145 0 0), ~19.5:1) and given a whisper of the theme hue so it
       reads as part of Aurora rather than stark monochrome against the
       magenta-tinted surfaces. Still WCAG AAA at 16.3:1. */
    --color-wk-text: oklch(0.24 0.02 var(--theme-hue));

    /* Inline <code> — CI magenta on a light magenta-tint highlight, so inline
       code reads as part of the brand. WCAG AA at 5.74:1, hue-stable across retints. */
    --color-wk-code: oklch(0.52 0.24 var(--theme-hue));
    --color-wk-code-bg: oklch(0.97 0.02 var(--theme-hue));

    --color-wk-border: oklch(0.91 0 0);
    /* Form-control borders — contrast-bound, not stylistic: 3:1 against the
       input fill (WCAG 1.4.11), and hover moves AWAY from that fill. The softer
       0.82 this preset used to carry reads well on a card edge but reaches only
       1.75:1 around an input. Keep in lockstep with docs/theming/aurora.md. */
    --color-wk-border-strong: oklch(0.66 0 0);
    --color-wk-border-strong-hover: oklch(0.58 0 0);

    --shadow-wk-sm: 0 1px 2px 0 oklch(0.2 0.03 var(--theme-hue) / 0.04), 0 1px 1px 0 oklch(0.2 0.03 var(--theme-hue) / 0.03);
    --shadow-wk-md: 0 4px 6px -1px oklch(0.2 0.03 var(--theme-hue) / 0.06), 0 2px 4px -2px oklch(0.2 0.03 var(--theme-hue) / 0.04);
    --shadow-wk-lg: 0 10px 15px -3px oklch(0.2 0.03 var(--theme-hue) / 0.08), 0 4px 6px -4px oklch(0.2 0.03 var(--theme-hue) / 0.05);
    --shadow-wk-xl: 0 20px 25px -5px oklch(0.2 0.03 var(--theme-hue) / 0.09), 0 8px 10px -6px oklch(0.2 0.03 var(--theme-hue) / 0.04);

    /* Softer badges — flat tinted chips (no outline, no inset ring) rather than
       the bordered default. Set --border-wk-badge-width / --shadow-wk-badge back
       to the global defaults in your own :root to restore the harder look. */
    --border-wk-badge-width: 0px;
    --shadow-wk-badge: none;

    --transition-wk-easing: cubic-bezier(0.16, 1, 0.3, 1);
CSS,
                'dark_vars' => <<<'CSS'
    /* Aurora dark — deepen the surfaces with the brand-hue undertone;
       --theme-hue is inherited from the light block (no redeclaration). */

    --color-wk-accent: oklch(0.68 0.2 var(--theme-hue));
    --color-wk-accent-hover: oklch(0.74 0.2 var(--theme-hue));
    --color-wk-accent-content: oklch(0.82 0.18 var(--theme-hue));
    --color-wk-accent-fg: oklch(0.15 0.025 var(--theme-hue));

    --color-wk-accent-brand: oklch(0.7 0.27 var(--theme-hue));
    --color-wk-accent-brand-fg: oklch(0.15 0.025 var(--theme-hue));

    --color-wk-bg: oklch(0.16 0.025 var(--theme-hue));
    --color-wk-bg-elevated: oklch(0.21 0.028 var(--theme-hue));
    --color-wk-bg-muted: oklch(0.235 0.027 var(--theme-hue));
    --color-wk-bg-subtle: oklch(0.275 0.025 var(--theme-hue));

    /* Foreground text — a barely-warm off-white instead of pure neutral
       (default oklch(0.985 0 0)), carrying the same whisper of theme hue as
       the light block so dark-mode body text harmonizes with Aurora's
       magenta surfaces. WCAG AAA at 17.3:1. */
    --color-wk-text: oklch(0.96 0.012 var(--theme-hue));

    /* Inline <code> — light magenta on a dark magenta-tint highlight. WCAG AA at 7.56:1. */
    --color-wk-code: oklch(0.8 0.16 var(--theme-hue));
    --color-wk-code-bg: oklch(0.27 0.04 var(--theme-hue));

    --color-wk-border: oklch(0.31 0.024 var(--theme-hue));
    /* Same floor as light, reached from the other side — on a dark fill the
       border brightens away from it. Verified across the whole --theme-hue
       range, not at one sample hue. */
    --color-wk-border-strong: oklch(0.52 0.02 var(--theme-hue));
    --color-wk-border-strong-hover: oklch(0.60 0.02 var(--theme-hue));

    --shadow-wk-sm: 0 1px 2px 0 oklch(0 0 0 / 0.30), 0 1px 1px 0 oklch(0 0 0 / 0.20);
    --shadow-wk-md: 0 4px 6px -1px oklch(0 0 0 / 0.35), 0 2px 4px -2px oklch(0.2 0.03 var(--theme-hue) / 0.25);
    --shadow-wk-lg: 0 10px 15px -3px oklch(0 0 0 / 0.40), 0 4px 6px -4px oklch(0.2 0.03 var(--theme-hue) / 0.30);
    --shadow-wk-xl: 0 20px 25px -5px oklch(0 0 0 / 0.45), 0 8px 10px -6px oklch(0.2 0.03 var(--theme-hue) / 0.30);
CSS,
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
