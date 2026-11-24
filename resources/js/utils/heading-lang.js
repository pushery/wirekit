/**
 * The language an outline's entry for a heading carries: the `lang` of the nearest element around
 * the heading that holds one, where it differs from the language around the outline, and `null`
 * where it does not.
 *
 * A table of contents, a spine or the minimap's heading anchors can stand outside the text they
 * list, and the two can be written in different languages: an `article` with `lang="en"` on a
 * page of a German site lists English headings in an outline that reads as German, and a screen
 * reader speaks each entry with the outline's pronunciation (WCAG 2.2, 3.1.2). An entry in the
 * outline's own language takes no attribute, so an outline in one language renders as it did.
 *
 * `null` rather than an empty string: Alpine removes an attribute bound to `null`, and an empty
 * `lang` would say that the language is unknown.
 *
 * @param {Element|null|undefined} heading
 * @param {Element|null|undefined} outline
 * @returns {string|null}
 */
export function headingLang(heading, outline) {
    const own = heading?.closest?.('[lang]')?.getAttribute?.('lang') ?? '';
    const around = outline?.closest?.('[lang]')?.getAttribute?.('lang') ?? '';

    return own !== '' && own.toLowerCase() !== around.toLowerCase() ? own : null;
}
