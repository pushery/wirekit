<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons marketing extension — stackable preset adding ~30 marketing &
 * landing-page aliases on top of the base Heroicons preset.
 *
 * All identifiers use the Mini (heroicon-m-*) style to match HeroiconsPreset.
 *
 * Activate by adding to wirekit.icons.presets in config/wirekit.php:
 *
 *     'presets' => ['heroicons', 'heroicons-marketing'],
 *
 * Aliases here have zero overlap with the base preset — verified by an
 * anti-drift test in IconSystemTest.
 *
 * @see https://heroicons.com
 */
final class HeroiconsMarketingPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Energy & motion
            'bolt' => 'heroicon-m-bolt',
            'sparkles' => 'heroicon-m-sparkles',
            'rocket-launch' => 'heroicon-m-rocket-launch',
            'fire' => 'heroicon-m-fire',
            'cursor-arrow-rays' => 'heroicon-m-cursor-arrow-rays',

            // Time & metrics
            'clock' => 'heroicon-m-clock',
            'chart-bar' => 'heroicon-m-chart-bar-square',
            'chart-pie' => 'heroicon-m-chart-pie',

            // Building blocks
            'cube' => 'heroicon-m-cube',
            'cube-transparent' => 'heroicon-m-cube-transparent',
            'squares-2x2' => 'heroicon-m-squares-2x2',
            'puzzle-piece' => 'heroicon-m-puzzle-piece',

            // Branding & creative
            'swatch' => 'heroicon-m-swatch',
            'paint-brush' => 'heroicon-m-paint-brush',
            'star' => 'heroicon-m-star',
            'heart' => 'heroicon-m-heart',

            // Trust & security
            'shield' => 'heroicon-m-shield-check',
            'shield-check' => 'heroicon-m-shield-check',
            'lock-closed' => 'heroicon-m-lock-closed',
            'finger-print' => 'heroicon-m-finger-print',

            // Audience
            'users' => 'heroicon-m-users',
            'user-group' => 'heroicon-m-user-group',
            'globe' => 'heroicon-m-globe-alt',

            // Developer / product
            'code-bracket' => 'heroicon-m-code-bracket',
            'command-line' => 'heroicon-m-command-line',
            'cog-6-tooth' => 'heroicon-m-cog-6-tooth',

            // Directional (marketing-oriented; base preset uses chevron-* for UI nav)
            'arrow-right' => 'heroicon-m-arrow-right',
            'arrow-left' => 'heroicon-m-arrow-left',
            'arrow-up-right' => 'heroicon-m-arrow-top-right-on-square',

            // Marketing-copy semantic aliases. Names map to landing-page
            // bullet copy ("live status", "AI feature", "open source") rather
            // than to the underlying icon name. Anti-collision verified by
            // IconSystemTest — none of these shadow a base alias.
            'live' => 'heroicon-m-signal',
            'pulse' => 'heroicon-m-arrow-path-rounded-square',
            'a11y' => 'heroicon-m-finger-print',
            'sparkle' => 'heroicon-m-sparkles',
            'security' => 'heroicon-m-lock-closed',
            'speed' => 'heroicon-m-bolt',
            'open-source' => 'heroicon-m-code-bracket',
            'ai' => 'heroicon-m-cpu-chip',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
