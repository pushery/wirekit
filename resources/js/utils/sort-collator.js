/**
 * The comparator a table sorts its text with: the application's locale, natural number order,
 * and no difference between cases or accents at the first level.
 *
 * The locale decides where a letter goes: German sorts "Ä" with "A", Swedish after "Z". Only the
 * server knows which one the page is written in, so the caller hands it over rather than the
 * runtime's default, which is the reader's browser. `numeric` puts "Item 9" before "Item 10".
 *
 * A tag the runtime rejects falls back to its default instead of throwing inside a sort.
 *
 * @param {string|null|undefined} locale  BCP-47, as the view spells it
 * @returns {Intl.Collator}
 */
export function sortCollator(locale) {
    const options = { numeric: true, sensitivity: 'base' };

    try {
        return new Intl.Collator(locale || undefined, options);
    } catch {
        return new Intl.Collator(undefined, options);
    }
}

export default sortCollator;
