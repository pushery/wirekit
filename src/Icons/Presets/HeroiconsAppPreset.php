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
 * every word it could add is already in the base.
 *
 * If it ever gains an alias again, the same rule applies as everywhere else: a name is
 * only taken when all four interchangeable presets have a genuine glyph for it.
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
