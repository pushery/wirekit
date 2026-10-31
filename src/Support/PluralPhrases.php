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
 * and the result deduplicates to two or three entries for most keys, with zero's
 * own entry where the browser would file zero under another form.
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
     * category — the client tries an exact match before it consults the rule —
     * and because the server's rule and the browser's can file zero apart.
     */
    public const SAMPLES = [0, 1, 2, 3, 5, 6, 7, 11, 21, 100];

    /**
     * Render one translation key at every sample count, keeping `:count` intact.
     *
     * The `:count` placeholder is preserved by replacing it with itself — the
     * translator substitutes, and what it substitutes is the placeholder, so the
     * template survives and the browser fills in the real number.
     *
     * `$replace` fills the placeholders the server already knows, such as a
     * title, in every form. `:count` stays the browser's to fill, whatever the
     * array says.
     *
     * Where the line's forms follow another language than the page's, as the
     * package's English line does under a locale it ships no catalog for, the map
     * names that language under `rule`, and the browser chooses by it.
     *
     * @param  list<int>  $samples
     * @param  array<string, string|int|float>  $replace
     * @return array<int|string, string> sample count -> template, and `rule` -> language
     */
    public static function from(string $key, ?array $samples = null, array $replace = []): array
    {
        $phrases = [];
        $seen = [];
        $ordered = $samples ?? self::SAMPLES;

        // Zero is reachable in the browser ONLY by exact match. The category loop
        // skips it, because `{0} no reactions` says something about the number
        // itself and must not stand in for every count that shares its category.
        // So zero takes no part in the deduplication below: rendered among the
        // others, it would claim whatever string it produced, and a later sample
        // with the same string would be dropped, leaving that form under a sample
        // nothing reaches. ("5 second", shipped.)
        //
        // And zero travels wherever the browser would write another form for it.
        // The browser files zero under a category by the CLDR rule, the server
        // writes it by Laravel's, and the two need not agree: Laravel writes
        // Portuguese zero in the plural, CLDR files it under `one` with the
        // singular. Only zero's own entry makes the browser write what the server
        // wrote, and where the two agree it would only repeat a form.
        $zero = array_search(0, $ordered, true);

        if ($zero !== false) {
            unset($ordered[$zero]);
        }

        foreach ($ordered as $count) {
            $rendered = self::render($key, $count, $replace);

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

        $rule = Plural::rule($key);

        if ($zero !== false) {
            $form = self::render($key, 0, $replace);

            if ($form !== self::browserForm($phrases, 0, $rule)) {
                $phrases = [0 => $form] + $phrases;
            }
        }

        if ($rule !== Plural::language(app()->getLocale())) {
            $phrases['rule'] = $rule;
        }

        return $phrases;
    }

    /**
     * The form the browser takes for a count from a map, as plural.js takes it: the
     * first sample in the count's CLDR category, otherwise the largest sample.
     *
     * Null where PHP has no CLDR rule to ask (the intl extension is not loaded), so
     * the caller sends the form rather than guess.
     *
     * @param  array<int, string>  $phrases
     */
    private static function browserForm(array $phrases, int $count, string $language): ?string
    {
        if (! class_exists(\MessageFormatter::class) || $phrases === []) {
            return null;
        }

        $category = static fn (int $n): string|false => \MessageFormatter::formatMessage(
            $language,
            '{n, plural, zero {zero} one {one} two {two} few {few} many {many} other {other}}',
            ['n' => $n],
        );
        $wanted = $category($count);

        if ($wanted === false) {
            return null;
        }

        foreach ($phrases as $sample => $form) {
            if ($sample !== 0 && $category($sample) === $wanted) {
                return $form;
            }
        }

        return $phrases[array_key_last($phrases)];
    }

    /**
     * One sample's form, trimmed.
     *
     * Trimmed, and not only for tidiness. A count that matches no range comes back
     * with the segment separator's whitespace still on it: `Plural::choice('{1} …|[2,*]
     * :count people reacted', 0)` returns " :count people reacted". Untrimmed that is a
     * distinct form differing by one space, and an announcement that begins with one.
     *
     * @param  array<string, string|int|float>  $replace
     */
    private static function render(string $key, int $count, array $replace): string
    {
        return trim(Plural::choice($key, $count, ['count' => ':count'] + $replace));
    }
}
