/**
 * Pick a translated plural form in the browser, for a value that changes there.
 *
 * A component whose accessible name embeds a COUNT cannot build that name on
 * the server once an optimistic layer can change the count: the page would show
 * six and announce five, and the rollback would put back a number the user was
 * never told about. So the forms travel to the client and the choosing happens
 * where the number lives.
 *
 * **The forms travel, and so does the rule.** Handing over "singular" and
 * "plural" is only enough for languages that have exactly those two. Polish has
 * three categories, Arabic six, and a `count === 1 ? a : b` picks the wrong one
 * without ever looking wrong to a developer who reads English. `Intl.PluralRules`
 * is the rule, and every browser in WireKit's support baseline has it.
 *
 * The server sends a map of SAMPLE COUNT to template, not a map of category to
 * template, and that is deliberate: PHP's intl extension does not expose CLDR
 * plural categories, so the server cannot name them. It can render the same
 * translation key for a handful of counts, and the client — which has the rule —
 * works out which sample belongs to the same category as the number it has.
 *
 * Laravel's exact-value syntax survives this: `{0} no reactions` renders at
 * sample 0, and an exact match is tried before any category is considered.
 *
 * @param {Object} phrases  sample count (as a key) -> template containing `:count`
 * @param {number} count    the number to render
 * @param {string} locale   BCP-47 tag; the application's locale, not the browser's
 * @returns {string}
 */
export function pluralize(phrases, count, locale) {
    if (! phrases || typeof phrases !== 'object') {
        return String(count);
    }

    // Exact first. Laravel lets a translation name a specific value — `{0} no
    // reactions` — and that is a statement about the number itself, not about
    // its plural category, so no rule may override it.
    if (Object.prototype.hasOwnProperty.call(phrases, String(count))) {
        return phrases[String(count)].replace(':count', String(count));
    }

    const keys = Object.keys(phrases);

    if (keys.length === 0) {
        return String(count);
    }

    let rules;

    try {
        rules = new Intl.PluralRules(locale || 'en');
    } catch {
        // An unusable locale tag must not take the announcement down with it.
        rules = new Intl.PluralRules('en');
    }

    const wanted = rules.select(count);

    for (const key of keys) {
        const sample = Number(key);

        // Skip the exact-value samples when matching by category: `{0}` exists
        // to say something about zero specifically, and letting it stand in for
        // the whole `other` category would announce "no reactions" for 42 in a
        // locale where both select the same way.
        if (! Number.isFinite(sample) || sample === 0) {
            continue;
        }

        if (rules.select(sample) === wanted) {
            return phrases[key].replace(':count', String(count));
        }
    }

    // No sample matched the category — send the last form rather than nothing.
    // A slightly wrong plural is a bad announcement; an empty one is silence,
    // and silence is the failure this whole layer exists to avoid.
    return phrases[keys[keys.length - 1]].replace(':count', String(count));
}
