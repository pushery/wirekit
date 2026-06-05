<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Deterministic avatar background palette.
 *
 * Maps an arbitrary key (typically a person's initials or name) to a stable
 * background + foreground color pair, so the same person always renders the
 * same avatar color across page loads and across a list. Replaces the
 * per-app `$avatarBg = fn ($initials) => …` crc32-palette helpers that every
 * dashboard blueprint previously hand-rolled.
 *
 * Theme-independence: each palette entry is a self-contained background +
 * white foreground pair chosen for WCAG-AA contrast (≥ 4.5:1) regardless of
 * the active WireKit theme — the avatar paints its own color, it does not
 * read a theme token, so it reads identically in light and dark. The
 * lightness (~47%) keeps white text above the AA threshold on every hue.
 */
final class AvatarPalette
{
    /**
     * Canonical background palette. Eight hues at a fixed lightness/chroma
     * tuned so `#fff` foreground clears WCAG AA (4.5:1) on every entry.
     * oklch() is in the Tailwind v4 browser baseline.
     *
     * @var list<string>
     */
    private const BACKGROUNDS = [
        'oklch(47% 0.13 25)',    // red
        'oklch(47% 0.12 60)',    // orange
        'oklch(47% 0.11 140)',   // green
        'oklch(47% 0.11 190)',   // teal
        'oklch(47% 0.13 250)',   // blue
        'oklch(47% 0.14 290)',   // violet
        'oklch(47% 0.15 330)',   // magenta
        'oklch(47% 0.12 110)',   // olive
    ];

    /** White foreground reads on every background above (AA-safe). */
    private const FOREGROUND = '#fff';

    /**
     * Resolve the deterministic color pair for a key.
     *
     * @return array{bg: string, fg: string}
     */
    public static function for(string $key): array
    {
        // crc32 is stable across requests + PHP versions (unlike the salted
        // hash of `random_*`), so the same key always lands on the same entry.
        $index = crc32($key) % count(self::BACKGROUNDS);

        return [
            'bg' => self::BACKGROUNDS[$index],
            'fg' => self::FOREGROUND,
        ];
    }

    /**
     * The full palette (for tooling / tests that need to enumerate entries).
     *
     * @return list<string>
     */
    public static function backgrounds(): array
    {
        return self::BACKGROUNDS;
    }
}
