<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The translated plural forms of one key, ready to be chosen from in the browser.
 *
 * A component whose accessible name embeds a COUNT cannot build that name on the
 * server once an optimistic layer can change the count: the page would show six
 * and announce five, and the rollback would put back a number the user was never
 * told about. The forms have to travel to where the number lives.
 *
 * **This sends sample counts, not CLDR categories, and that is not a shortcut.**
 * PHP's intl extension does not expose plural categories, so the server cannot
 * name them — it can only render the same key at a handful of counts. The
 * client has `Intl.PluralRules`, so it works out which sample shares a category
 * with the number it holds. The rule lives on the side that has it.
 *
 * The sample set covers the categories real locales use: English and German need
 * 1 and 2; Polish and Russian need 1, 2 and 5; Welsh needs 0, 1, 2, 3, 6 and the
 * rest; Irish needs 7. Rendering a key ten times costs nothing at render time
 * and the result deduplicates to two or three entries for most keys.
 *
 * @see resources/js/utils/plural.js — the half that chooses
 */
final class PluralPhrases
{
    /**
     * Counts that reach every plural category the supported locales distinguish.
     *
     * Zero is in the list for Laravel's exact-value syntax (`{0} no reactions`),
     * which says something about the number itself rather than about its
     * category — the client tries an exact match before it consults the rule.
     */
    public const SAMPLES = [0, 1, 2, 3, 5, 6, 7, 11, 21, 100];

    /**
     * Render one translation key at every sample count, keeping `:count` intact.
     *
     * The `:count` placeholder is preserved by replacing it with itself — the
     * translator substitutes, and what it substitutes is the placeholder, so the
     * template survives and the browser fills in the real number.
     *
     * @param  list<int>  $samples
     * @return array<int, string> sample count -> template
     */
    public static function from(string $key, ?array $samples = null): array
    {
        $phrases = [];
        $seen = [];

        $ordered = $samples ?? self::SAMPLES;

        // Zero goes LAST, and the whole correctness of the pair rests on it.
        //
        // Zero is reachable in the browser ONLY by exact match — the category
        // loop skips it deliberately, because `{0} no reactions` says something
        // about the number itself and must not stand in for every count that
        // shares its category. Rendered FIRST, it claimed whatever string it
        // produced, and every later sample that produced the same string was
        // dropped as a duplicate. For a key with no `{0}` rule that string is
        // the plural — Laravel answers an unmatched count with its last segment
        // — so the plural ended up filed under a sample nothing could reach and
        // every count above one read as a singular. ("5 second", shipped.)
        //
        // Rendered last, zero keeps its entry only when it says something no
        // category sample already says, which is exactly when it has its own
        // rule. That is the case it was added for, and the only one it earns.
        $zero = array_search(0, $ordered, true);

        if ($zero !== false) {
            unset($ordered[$zero]);
            $ordered[] = 0;
        }

        foreach ($ordered as $count) {
            // Trimmed, and not only for tidiness. A count that matches no range
            // comes back with the segment separator's whitespace still on it —
            // measured: `trans_choice('{1} …|[2,*] :count people reacted', 0)`
            // returns " :count people reacted". Untrimmed that is a THIRD
            // distinct form differing by one space, and an announcement that
            // begins with one.
            $rendered = trim(trans_choice($key, $count, ['count' => ':count']));

            // Deduplicate by rendered form. Six samples that all produce
            // ":count people reacted" would ship six identical strings into
            // every page; the first one of each distinct form is enough,
            // because the client matches a category and any sample in that
            // category answers for it.
            if (in_array($rendered, $seen, true)) {
                continue;
            }

            $seen[] = $rendered;
            $phrases[$count] = $rendered;
        }

        // Sorted by count, so the map's LAST entry is its largest sample. The
        // browser falls back to the last form when no category matches, and a
        // fallback that is a `{0}` phrase would answer an unmatched plural with
        // "no reactions" — the one wrong answer worse than a wrong plural.
        ksort($phrases);

        return $phrases;
    }
}
