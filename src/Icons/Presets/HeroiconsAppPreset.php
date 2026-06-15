<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons app extension — stackable preset adding ~12 high-frequency
 * app-state aliases on top of the base Heroicons preset.
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
            'arrow-up' => 'heroicon-m-arrow-up',
            'arrow-down' => 'heroicon-m-arrow-down',

            // Close-with-emphasis (vs. plain `close` from base preset)
            'x-circle' => 'heroicon-m-x-circle',

            // Sharing / clipboard
            // (`book` / `lightbulb` / `copy` moved to the base preset in v2.6.4 —
            // they resolve on every base preset now, no stacking required.)
            'link' => 'heroicon-m-link',

            // Security states
            'lock' => 'heroicon-m-lock-closed',
            'unlock' => 'heroicon-m-lock-open',
            'key' => 'heroicon-m-key',

            // Notifications
            'bell' => 'heroicon-m-bell',
            'bell-slash' => 'heroicon-m-bell-slash',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
