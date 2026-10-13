<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons app extension — empty, and deliberately kept.
 *
 * Its words live in the four base presets, so they are reachable from a lucide,
 * phosphor or tabler install as well. An alias here would emit a heroicon identifier
 * exclusively: stacked onto another family, it would resolve a name onto a glyph that
 * family does not ship, and blade-icons throws rather than degrading.
 *
 * It stays registered rather than being deleted. An application with
 * `'presets' => [..., 'heroicons-app', ...]` in its published config would otherwise
 * fail to boot on an unknown preset name — a hard break in exchange for removing a
 * class that costs nothing. Stacking it is simply a no-op: it adds no keys, and
 * every word it could add is already in the base.
 *
 * If it gains an alias, the same rule applies as everywhere else: a name is only
 * taken when all four interchangeable presets have a genuine glyph for it.
 *
 * Activate by adding to wirekit.icons.presets in config/wirekit.php:
 *
 *     'presets' => ['heroicons', 'heroicons-app', 'heroicons-marketing'],
 *
 * The set is empty: no identifiers and no aliases, so it makes no promise about the Mini style
 * or about overlapping the base and marketing presets. If this file gains an alias, both apply,
 * and so does the rule stated above: a name is only taken when all four interchangeable
 * presets have a genuine glyph for it.
 *
 * @see https://heroicons.com
 */
final class HeroiconsAppPreset implements IconPreset
{
    public function icons(): array
    {
        // Deliberately empty — see the class docblock. It carries no group headings either,
        // so the array reads as an intentional no-op rather than as one somebody half-deleted.
        return [];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
