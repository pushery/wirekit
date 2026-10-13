<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons marketing extension — a stackable preset carrying the marketing and
 * landing-page names the base Heroicons preset does not.
 *
 * It is deliberately small: the common semantic names are in every base preset, so
 * the vocabulary a landing page usually reaches for resolves without stacking this at
 * all. What is here are the names that are heroicons-specific; icons() is the current
 * set.
 *
 * All identifiers use the Mini (heroicon-m-*) style to match HeroiconsPreset.
 *
 * Activate by adding to wirekit.icons.presets in config/wirekit.php:
 *
 *     'presets' => ['heroicons', 'heroicons-marketing'],
 *
 * Aliases here have zero overlap with the base preset.
 *
 * @see https://heroicons.com
 */
final class HeroiconsMarketingPreset implements IconPreset
{
    // An extension ADDS words, it does not redefine them. A shared alias such as `chart-bar`
    // belongs to the BASE vocabulary, where it means the same thing whichever preset you
    // install; declared here it would mean `chart-bar-square` on Heroicons and nothing at all
    // on the other three families.
    public function icons(): array
    {
        return [
            // Energy & motion
            // `bolt` is a base word, so it is not declared here.
            'cursor-arrow-rays' => 'heroicon-m-cursor-arrow-rays',

            // Time & metrics
            // `pulse` stays HERE rather than in the base presets, and that is the
            // vocabulary rule doing its job. Lucide, phosphor and tabler all draw an
            // EKG trace for this word; heroicons has no waveform glyph at all — its
            // nearest neighbor is `arrow-path-rounded-square`, a two-arrow refresh
            // cycle. In the base presets one word would mean "live signal" on three
            // sets and "repeat" on the fourth, which is the silent iconography change
            // the rule exists to prevent. Heroicons-only, it means one thing.
            'pulse' => 'heroicon-m-arrow-path-rounded-square',

            // Building blocks
            'cube-transparent' => 'heroicon-m-cube-transparent',

            // Branding & creative
            // `paint-brush` is a base word, so it is not declared here; the base presets
            // resolve it in every documented configuration.
            // `cursor-arrow-rays` stays below: that is heroicons' spelling of its own
            // glyph, and the concept is the base word `click`.

            // Trust & security
            // `shield` and `shield-check` are base words, so they are not declared here.

            // Audience
            // `globe`, `live` and `users` are base words, so they are not declared here:
            // managing accounts is not a marketing concept, it is what every signed-in
            // application has.

            // Developer / product

            // Directional (marketing-oriented; base preset uses chevron-* for UI nav)

            // Marketing-copy semantic aliases. Names map to landing-page
            // bullet copy ("live status", "AI feature", "open source") rather
            // than to the underlying icon name. None of these shadow a base alias.
            'a11y' => 'heroicon-m-finger-print',
            'sparkle' => 'heroicon-m-sparkles',
            'ai' => 'heroicon-m-cpu-chip',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
