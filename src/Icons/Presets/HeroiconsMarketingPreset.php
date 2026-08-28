<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons marketing extension — a stackable preset carrying the marketing and
 * landing-page names the base Heroicons preset does not.
 *
 * It is deliberately small, and it got smaller: v2.37.0 moved the common semantic
 * names into every base preset, so the vocabulary a landing page usually reaches for
 * resolves without stacking this at all. What is left here are the names that stayed
 * heroicons-specific. Read icons() for the current set rather than trusting a number
 * in a comment — this line said "~30" for four minors while the file declared seven.
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
    // `chart-bar` was here and moved to the BASE vocabulary, where it belongs: an
    // extension ADDS words, it does not redefine them. While this preset owned the name it
    // meant `chart-bar-square` on Heroicons and nothing at all on the other three families
    // — so the one property a shared alias has to keep, that it means the same thing
    // whichever preset you install, was the property it did not have.
    public function icons(): array
    {
        return [
            // Energy & motion
            // `bolt` promoted to the base presets in v2.12.0 — removed here to
            // keep the marketing extension non-overlapping with the base set.
            'cursor-arrow-rays' => 'heroicon-m-cursor-arrow-rays',

            // Time & metrics
            // `pulse` stays HERE rather than in the base presets, and that is the
            // vocabulary rule doing its job. Lucide, phosphor and tabler all draw an
            // EKG trace for this word; heroicons has no waveform glyph at all — its
            // nearest neighbor is `arrow-path-rounded-square`, a two-arrow refresh
            // cycle. Promoting it would have made one word mean "live signal" on three
            // sets and "repeat" on the fourth, which is the silent iconography change
            // the rule exists to prevent. Heroicons-only, it means one thing.
            'pulse' => 'heroicon-m-arrow-path-rounded-square',

            // Building blocks
            'cube-transparent' => 'heroicon-m-cube-transparent',

            // Branding & creative
            'paint-brush' => 'heroicon-m-paint-brush',

            // Trust & security
            // `shield` / `shield-check` promoted to the base presets in v2.12.0 —
            // removed here to keep the marketing extension non-overlapping.

            // Audience
            // (`globe` moved to the base preset in v2.6.4; `live` stays here —
            // no clean universal-core equivalent across icon libraries.)
            // `users` moved to the BASE preset in v2.24.0 and is removed here, so a
            // stacked setup does not define it twice. Same glyph either way
            // (`heroicon-m-users`), so nothing a caller renders changes — this is
            // the same promotion `copy`, `globe`, `book` and `lightbulb` went
            // through in v2.6.4, for the same reason: managing accounts is not a
            // marketing concept, it is what every signed-in application has.

            // Developer / product

            // Directional (marketing-oriented; base preset uses chevron-* for UI nav)

            // Marketing-copy semantic aliases. Names map to landing-page
            // bullet copy ("live status", "AI feature", "open source") rather
            // than to the underlying icon name. Anti-collision verified by
            // IconSystemTest — none of these shadow a base alias.
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
