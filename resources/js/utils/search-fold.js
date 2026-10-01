/**
 * Text as a search compares it: accents folded away and lower-cased, so a reader who types
 * `munchen` finds `München` and `istanbul` finds `İstanbul`.
 *
 * NFD splits a letter into its base and its combining marks, and stripping the marks leaves the
 * base. `toLowerCase()` alone turns the Turkish dotted capital into `i` plus a combining dot,
 * which a typed `i` never matches. A table of replacements would be wrong for the next language
 * somebody uses; a letter that is not a base with marks, such as `ß` or `æ`, stays itself.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function foldForSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();
}

export default foldForSearch;
