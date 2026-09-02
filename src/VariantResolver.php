<?php

declare(strict_types=1);

namespace Pushery\WireKit;

/**
 * Resolves intent × surface combinations into CSS class strings.
 *
 * Intents: primary, neutral, success, warning, danger, info
 * Surfaces: filled, outline, soft, ghost, link
 *
 * Old-style `variant` values (e.g. "primary", "danger") are mapped
 * to intent+surface pairs for backward compatibility.
 */
class VariantResolver
{
    public const INTENTS = ['primary', 'neutral', 'success', 'warning', 'danger', 'info'];

    public const SURFACES = ['filled', 'outline', 'soft', 'ghost', 'link'];

    /**
     * Resolve intent and surface into CSS classes for button-like components.
     */
    public static function resolve(string $intent, string $surface): string
    {
        return match ($surface) {
            'filled' => self::filled($intent),
            'outline' => self::outline($intent),
            'soft' => self::soft($intent),
            'ghost' => self::ghost($intent),
            'link' => self::link($intent),
            default => '',
        };
    }

    /**
     * Map a legacy variant string to an intent+surface pair.
     *
     * @return array{intent: string, surface: string}
     */
    public static function fromVariant(string $variant): array
    {
        return match ($variant) {
            'primary' => ['intent' => 'primary', 'surface' => 'filled'],
            'secondary' => ['intent' => 'neutral', 'surface' => 'filled'],
            'outline' => ['intent' => 'neutral', 'surface' => 'outline'],
            'ghost' => ['intent' => 'neutral', 'surface' => 'ghost'],
            'danger' => ['intent' => 'danger', 'surface' => 'filled'],
            'link' => ['intent' => 'primary', 'surface' => 'link'],
            'success' => ['intent' => 'success', 'surface' => 'filled'],
            'warning' => ['intent' => 'warning', 'surface' => 'filled'],
            'info' => ['intent' => 'info', 'surface' => 'filled'],
            'neutral' => ['intent' => 'neutral', 'surface' => 'filled'],
            default => ['intent' => 'primary', 'surface' => 'filled'],
        };
    }

    private static function filled(string $intent): string
    {
        return match ($intent) {
            'primary' => implode(' ', [
                'bg-[var(--color-wk-accent)]',
                'text-[color:var(--color-wk-accent-fg)]',
                'border-[var(--color-wk-accent)]',
                'hover:bg-[var(--color-wk-accent-hover)]',
                'hover:border-[var(--color-wk-accent-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'neutral' => implode(' ', [
                'bg-[var(--color-wk-bg-muted)]',
                'text-[color:var(--color-wk-text)]',
                'border-[var(--color-wk-bg-muted)]',
                'hover:bg-[var(--color-wk-bg-subtle)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'success' => implode(' ', [
                'bg-[var(--color-wk-success)]',
                'text-[color:var(--color-wk-success-fg)]',
                'border-[var(--color-wk-success)]',
                'hover:bg-[var(--color-wk-success-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'warning' => implode(' ', [
                'bg-[var(--color-wk-warning)]',
                'text-[color:var(--color-wk-warning-fg)]',
                'border-[var(--color-wk-warning)]',
                'hover:bg-[var(--color-wk-warning-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'danger' => implode(' ', [
                'bg-[var(--color-wk-danger)]',
                'text-[color:var(--color-wk-danger-fg)]',
                'border-[var(--color-wk-danger)]',
                'hover:bg-[var(--color-wk-danger-hover)]',
                'hover:border-[var(--color-wk-danger-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            // 'info' is a visual synonym of 'primary' — both tint with the
            // accent color. The tokens --color-wk-info / --color-wk-info-fg /
            // --color-wk-info-hover do NOT exist in dist/wirekit.css; only
            // --color-wk-info-text exists (aliases accent-content). Reuse
            // the accent token chain so the button is theme-aware and the
            // CssTokenDriftTest guard passes.
            'info' => implode(' ', [
                'bg-[var(--color-wk-accent)]',
                'text-[color:var(--color-wk-accent-fg)]',
                'border-[var(--color-wk-accent)]',
                'hover:bg-[var(--color-wk-accent-hover)]',
                'hover:border-[var(--color-wk-accent-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            default => '',
        };
    }

    private static function outline(string $intent): string
    {
        // info aliases the accent token chain — see filled() above.
        $borderColor = match ($intent) {
            'primary', 'info' => '--color-wk-accent',
            'neutral' => '--color-wk-border',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            default => '--color-wk-border',
        };

        $textColor = match ($intent) {
            'primary', 'info' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success-text',
            'warning' => '--color-wk-warning-text',
            'danger' => '--color-wk-danger-text',
            default => '--color-wk-text',
        };

        return implode(' ', [
            'bg-[var(--color-wk-bg)]',
            "text-[color:var({$textColor})]",
            "border-[var({$borderColor})]",
            'hover:bg-[var(--color-wk-bg-subtle)]',
            'shadow-[var(--shadow-wk-sm)]',
        ]);
    }

    private static function soft(string $intent): string
    {
        // Soft = tinted background, intent-colored text, no border. The
        // *-bg tokens (--color-wk-accent-bg / --color-wk-success-bg / etc.)
        // do NOT exist in dist/wirekit.css — only --color-wk-warning-bg
        // exists. Use color-mix(in_srgb, var(--color-wk-X) 12%, var(--color-wk-bg))
        // for every intent, mirroring the badge component's soft-tint formula.
        // info aliases the accent chain.
        $tintToken = match ($intent) {
            'primary', 'info' => '--color-wk-accent',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            'neutral' => null, // neutral has its own bg-muted
            default => null,
        };

        $textColor = match ($intent) {
            'primary', 'info' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success-text',
            'warning' => '--color-wk-warning-text',
            'danger' => '--color-wk-danger-text',
            default => '--color-wk-text',
        };

        $bgClass = $tintToken === null
            ? 'bg-[var(--color-wk-bg-muted)]'
            : "bg-[color-mix(in_srgb,var({$tintToken})_12%,var(--color-wk-bg))]";

        // The hover, and `soft` was the only one of the five surfaces without one. That is not
        // a polish gap: `soft` is the surface for secondary actions, so it is the MAJORITY of
        // the buttons in an application — measured in one adopting app at 41 of 154 explicit,
        // with every row-action card built from nothing else. An element that does not answer
        // the pointer reads as not clickable, which is the signal a pointer user has.
        //
        // Same formula, more tint. 12% to 18% stays inside the expression the surface already
        // uses, needs no new token, and follows the theme in both modes because
        // `--color-wk-bg` is the half that switches.
        //
        // ⚠️ `neutral` is NOT `bg-subtle`, and the difference is the whole reason it is written
        // out here. `filled()` pairs muted with subtle, and measured against a real theme that
        // is L=0.972 → L=0.985 in light mode: the hover gets BRIGHTER by 1.3 points, which is
        // at the threshold of perception and pointing the way a reader parses as fading out.
        // Mixing the surface toward the TEXT color instead is correct in both modes by
        // construction — the text is dark on a light theme and light on a dark one, so the
        // hover always moves AWAY from the background rather than in a fixed direction.
        $hoverClass = $tintToken === null
            ? 'hover:bg-[color-mix(in_srgb,var(--color-wk-text)_6%,var(--color-wk-bg-muted))]'
            : "hover:bg-[color-mix(in_srgb,var({$tintToken})_18%,var(--color-wk-bg))]";

        return implode(' ', [
            $bgClass,
            "text-[color:var({$textColor})]",
            'border-transparent',
            $hoverClass,
        ]);
    }

    private static function ghost(string $intent): string
    {
        // info aliases the accent chain — see filled().
        $textColor = match ($intent) {
            'primary', 'info' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success-text',
            'warning' => '--color-wk-warning-text',
            'danger' => '--color-wk-danger-text',
            default => '--color-wk-text',
        };

        return implode(' ', [
            'bg-transparent',
            "text-[color:var({$textColor})]",
            'border-transparent',
            'hover:bg-[var(--color-wk-bg-subtle)]',
            'shadow-[var(--shadow-wk-none)]',
        ]);
    }

    private static function link(string $intent): string
    {
        // Use --color-wk-danger-text (calibrated for ≥4.5:1 against bg) for
        // danger links, not --color-wk-danger (the bright red used as a
        // surface fill — fails contrast on text). Same alignment as
        // outline() / soft() / ghost() above.
        $textColor = match ($intent) {
            'primary' => '--color-wk-accent-content',
            'danger' => '--color-wk-danger-text',
            default => '--color-wk-accent-content',
        };

        return implode(' ', [
            "text-[color:var({$textColor})]",
            'border-transparent',
            'underline-offset-4',
            'hover:underline',
            'p-0 h-auto',
        ]);
    }
}
