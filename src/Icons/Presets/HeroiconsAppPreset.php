<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons app extension — EMPTY since 2026-08-26, and deliberately kept.
 *
 * Every alias it carried has moved into the four base presets, so the words are now
 * reachable from a lucide, phosphor or tabler install as well. That was the whole
 * complaint: these aliases emitted heroicon identifiers exclusively, so stacking this
 * preset onto another family resolved a name onto a glyph that family does not ship,
 * and blade-icons threw rather than degrading.
 *
 * It stays registered rather than being deleted. An application with
 * `'presets' => [..., 'heroicons-app', ...]` in its published config would otherwise
 * fail to boot on an unknown preset name — a hard break in exchange for removing a
 * class that now costs nothing. Stacking it is simply a no-op: it adds no keys, and
 * the words it used to add are already in the base.
 *
 * If it ever gains an alias again, the same rule applies as everywhere else: a name is
 * only taken when all four interchangeable presets have a genuine glyph for it.
 *
 * All identifiers use the Mini (heroicon-m-*) style to match HeroiconsPreset
 * and HeroiconsMarketingPreset.
 *
 * Activate by adding to wirekit.icons.presets in config/wirekit.php:
 *
 *     'presets' => ['heroicons', 'heroicons-app', 'heroicons-marketing'],
 *
 * Aliases here have zero overlap with both the base preset AND the
 * marketing preset — verified by anti-drift tests in IconSystemTest.
 *
 * @see https://heroicons.com
 */
final class HeroiconsAppPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Sort order (table headers)

            // Close-with-emphasis (vs. plain `close` from base preset)

            // Sharing / clipboard
            // (`book` / `lightbulb` / `copy` moved to the base preset in v2.6.4 —
            // they resolve on every base preset now, no stacking required.)

            // Security states

            // Notifications
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
